<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Backup nocturno de la BD (mysqldump) y del storage privado de fotos de empaque.
 * Se conserva `--keep` días. Sube a `storage/app/backups/`.
 * En producción sumar sync a S3/DigitalOcean/rsync.
 */
class BackupDbFotos extends Command
{
    protected $signature = 'gb:backup {--keep=14 : Días de retención de backups locales}';

    protected $description = 'Backup diario: mysqldump + tar de storage/app/private/empaques';

    public function handle(): int
    {
        $stamp = now()->format('Ymd-His');
        $dir = storage_path('app/backups');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        // 1. BD dump
        $sqlFile = "{$dir}/db-{$stamp}.sql";
        $cmd = ['mysqldump', "-h{$host}", "-P{$port}", "-u{$user}"];
        if (! empty($pass)) $cmd[] = "-p{$pass}";
        $cmd = array_merge($cmd, ['--single-transaction', '--routines', '--triggers', $db]);

        $proc = new Process($cmd);
        $proc->setTimeout(600);
        $proc->run(function ($type, $buffer) use ($sqlFile) {
            if ($type === Process::OUT) {
                file_put_contents($sqlFile, $buffer, FILE_APPEND);
            }
        });
        if (! $proc->isSuccessful()) {
            $this->error('mysqldump falló: ' . $proc->getErrorOutput());
            return self::FAILURE;
        }
        $this->info("BD: {$sqlFile} (" . number_format(filesize($sqlFile)/1024, 1) . " KB)");

        // 2. Copiar directorio de fotos comprimido
        $fotosDir = storage_path('app/private/empaques');
        $tarFile = "{$dir}/fotos-{$stamp}.tar.gz";
        if (File::exists($fotosDir)) {
            $tarCmd = new Process(['tar', '-czf', $tarFile, '-C', dirname($fotosDir), basename($fotosDir)]);
            $tarCmd->setTimeout(600);
            $tarCmd->run();
            if ($tarCmd->isSuccessful()) {
                $this->info("Fotos: {$tarFile} (" . number_format(filesize($tarFile)/1024, 1) . " KB)");
            } else {
                $this->warn('Backup de fotos falló: ' . $tarCmd->getErrorOutput());
            }
        }

        // 3. Prune backups viejos (>keep días)
        $keep = (int) $this->option('keep');
        $eliminados = 0;
        foreach (File::files($dir) as $f) {
            if ($f->getMTime() < now()->subDays($keep)->timestamp) {
                File::delete($f->getPathname());
                $eliminados++;
            }
        }
        $this->info("Prune: eliminados {$eliminados} backups > {$keep} días.");

        return self::SUCCESS;
    }
}
