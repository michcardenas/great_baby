<?php

namespace App\Modules\Siigo\Support;

/**
 * Códigos DANE de ciudad y departamento, que es lo que SIIGO pide para crear
 * un tercero (`address.city.state_code` + `city_code`).
 *
 * Hace falta porque el ERP guarda la ciudad como texto libre ("Medellín") y
 * SIIGO la rechaza: «The field city_code is required». Tampoco sirve pedírsela
 * a la API: SIIGO no publica un endpoint de ciudades (404 en `/v1/cities`,
 * `/v1/regions` y `/v1/countries/CO/cities`), así que la tabla va acá.
 *
 * Están las ciudades donde Great Baby despacha hoy más las capitales. Si
 * aparece una que no figura, se usa la del setting `siigo.ciudad_default`
 * (Bogotá si no está configurado) y se deja constancia en el log: es preferible
 * que la factura salga con la ciudad de la empresa a que se caiga la venta.
 *
 * La clave de cada fila es el nombre como se le muestra a la gente; la
 * búsqueda normaliza (minúsculas, sin tildes) antes de comparar, así que
 * "MEDELLIN", "medellín" y "Medellín, Antioquia" caen en la misma fila.
 */
final class CiudadesDane
{
    /** Nombre presentable => [state_code, city_code] */
    private const CIUDADES = [
        'Bogotá D.C.' => ['11', '11001'],
        'Medellín' => ['05', '05001'],
        'Envigado' => ['05', '05266'],
        'Itagüí' => ['05', '05360'],
        'Bello' => ['05', '05088'],
        'Rionegro' => ['05', '05615'],
        'Cali' => ['76', '76001'],
        'Palmira' => ['76', '76520'],
        'Buenaventura' => ['76', '76109'],
        'Barranquilla' => ['08', '08001'],
        'Soledad' => ['08', '08758'],
        'Cartagena' => ['13', '13001'],
        'Bucaramanga' => ['68', '68001'],
        'Floridablanca' => ['68', '68276'],
        'Girón' => ['68', '68307'],
        'Piedecuesta' => ['68', '68547'],
        'Cúcuta' => ['54', '54001'],
        'Pereira' => ['66', '66001'],
        'Dosquebradas' => ['66', '66170'],
        'Manizales' => ['17', '17001'],
        'Armenia' => ['63', '63001'],
        'Ibagué' => ['73', '73001'],
        'Villavicencio' => ['50', '50001'],
        'Santa Marta' => ['47', '47001'],
        'Montería' => ['23', '23001'],
        'Valledupar' => ['20', '20001'],
        'Pasto' => ['52', '52001'],
        'Neiva' => ['41', '41001'],
        'Popayán' => ['19', '19001'],
        'Sincelejo' => ['70', '70001'],
        'Tunja' => ['15', '15001'],
        'Riohacha' => ['44', '44001'],
        'Quibdó' => ['27', '27001'],
        'Florencia' => ['18', '18001'],
        'Yopal' => ['85', '85001'],
        'Arauca' => ['81', '81001'],
        'Mocoa' => ['86', '86001'],
        'San Andrés' => ['88', '88001'],
        'Leticia' => ['91', '91001'],
        'Soacha' => ['25', '25754'],
        'Chía' => ['25', '25175'],
        'Zipaquirá' => ['25', '25899'],
        'Mosquera' => ['25', '25473'],
        'Funza' => ['25', '25286'],
        'Madrid' => ['25', '25430'],
        'Facatativá' => ['25', '25269'],
    ];

    /** Cómo la escribe la gente (normalizado) => cómo figura arriba. */
    private const ALIAS = [
        'bogota' => 'Bogotá D.C.',
        'bogota dc' => 'Bogotá D.C.',
        'santafe de bogota' => 'Bogotá D.C.',
    ];

    /** state_code => departamento, para poder autocompletarlo en el formulario. */
    private const DEPARTAMENTOS = [
        '05' => 'Antioquia',
        '08' => 'Atlántico',
        '11' => 'Bogotá D.C.',
        '13' => 'Bolívar',
        '15' => 'Boyacá',
        '17' => 'Caldas',
        '18' => 'Caquetá',
        '19' => 'Cauca',
        '20' => 'Cesar',
        '23' => 'Córdoba',
        '25' => 'Cundinamarca',
        '27' => 'Chocó',
        '41' => 'Huila',
        '44' => 'La Guajira',
        '47' => 'Magdalena',
        '50' => 'Meta',
        '52' => 'Nariño',
        '54' => 'Norte de Santander',
        '63' => 'Quindío',
        '66' => 'Risaralda',
        '68' => 'Santander',
        '70' => 'Sucre',
        '73' => 'Tolima',
        '76' => 'Valle del Cauca',
        '81' => 'Arauca',
        '85' => 'Casanare',
        '86' => 'Putumayo',
        '88' => 'Archipiélago de San Andrés',
        '91' => 'Amazonas',
    ];

    /**
     * Devuelve ['state_code' => ..., 'city_code' => ...] para una ciudad.
     * Nunca devuelve vacío: si no la reconoce usa la predeterminada.
     *
     * @return array{state_code: string, city_code: string, exacta: bool}
     */
    public static function resolver(?string $ciudad): array
    {
        if ($fila = self::buscar($ciudad)) {
            [$st, $ct] = $fila;

            return ['state_code' => $st, 'city_code' => $ct, 'exacta' => true];
        }

        $default = (string) (function_exists('setting') ? setting('siigo.ciudad_default', '11001') : '11001');
        $default = preg_match('/^\d{5}$/', $default) ? $default : '11001';

        return [
            'state_code' => substr($default, 0, 2),
            'city_code' => $default,
            'exacta' => false,
        ];
    }

    /** ¿Esta ciudad va a salir con su propio código DANE en la factura? */
    public static function reconoce(?string $ciudad): bool
    {
        return self::buscar($ciudad) !== null;
    }

    /**
     * Las ciudades que el ERP sabe traducir, para ofrecerlas en el formulario
     * en vez de dejar que se escriban a mano.
     *
     * @return list<array{ciudad: string, departamento: string, city_code: string}>
     */
    public static function listado(): array
    {
        $filas = [];
        foreach (self::CIUDADES as $nombre => [$st, $ct]) {
            $filas[] = [
                'ciudad' => $nombre,
                'departamento' => self::DEPARTAMENTOS[$st] ?? '',
                'city_code' => $ct,
            ];
        }

        // Se ordena por el nombre sin tildes: con `strcoll` y la locale "C",
        // "Ibagué" se iba al final de la lista por el byte de la tilde.
        usort($filas, fn ($a, $b) => self::normalizar($a['ciudad']) <=> self::normalizar($b['ciudad']));

        return $filas;
    }

    /** @return array{0: string, 1: string}|null */
    private static function buscar(?string $ciudad): ?array
    {
        $clave = self::normalizar($ciudad);
        if ($clave === '') {
            return null;
        }

        if (isset(self::ALIAS[$clave])) {
            return self::CIUDADES[self::ALIAS[$clave]];
        }

        // Índice normalizado de los nombres presentables; se arma una vez por
        // petición porque la tabla es chica y así no se duplica la lista.
        static $indice = null;
        if ($indice === null) {
            $indice = [];
            foreach (self::CIUDADES as $nombre => $codigos) {
                $indice[self::normalizar($nombre)] = $codigos;
            }
        }

        return $indice[$clave] ?? null;
    }

    /** "Medellín, Antioquia" → "medellin" */
    private static function normalizar(?string $v): string
    {
        $v = trim(mb_strtolower((string) $v));
        if ($v === '') return '';

        $v = explode(',', $v)[0];
        $v = strtr($v, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        $v = preg_replace('/[^a-z ]/', '', $v);

        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }
}
