<?php
declare(strict_types=1);

const RMS_NAME = 'Holiday Home Apartments RMS';
const RMS_DB = __DIR__ . '/data/rms-data.json';
const RMS_LOCK = __DIR__ . '/data/rms-data.lock';
const RMS_ADMIN_USERNAME = 'admin';
const RMS_ADMIN_PASSWORD_HASH = '$2y$12$NF1w60wo7e3qHU1duQyvPuw2ue4xvAjrTO5uq/JXcxiSEdgelWIiu';
const RMS_CURRENCY = 'USD';
const RMS_TIMEZONE = 'Africa/Freetown';

date_default_timezone_set(RMS_TIMEZONE);

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) { http_response_code(419); exit('Invalid CSRF token'); }
}
function admin_logged_in(): bool { return !empty($_SESSION['admin']); }
function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
function nights_between(string $in, string $out): int { return (int)((new DateTime($in))->diff(new DateTime($out))->days); }
function reservation_reference(): string { return 'HHA-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }

function default_store(): array {
    return [
        'next_property_id' => 4,
        'next_reservation_id' => 1,
        'properties' => [
            ['id'=>1,'name'=>'Standard Suite','location'=>'SS Camp, Freetown','description'=>'Comfortable serviced suite for short and long stays.','rate'=>100,'rate_period'=>'night','bedrooms'=>1,'bathrooms'=>1,'capacity'=>2,'status'=>'active','created_at'=>date('c')],
            ['id'=>2,'name'=>'Executive Suite','location'=>'Freetown','description'=>'Spacious executive suite designed for business and leisure stays.','rate'=>160,'rate_period'=>'night','bedrooms'=>2,'bathrooms'=>2,'capacity'=>4,'status'=>'active','created_at'=>date('c')],
            ['id'=>3,'name'=>'Luxury Penthouse','location'=>'Hill Station, Freetown','description'=>'Premium long-stay penthouse with generous space and privacy.','rate'=>2500,'rate_period'=>'month','bedrooms'=>3,'bathrooms'=>3,'capacity'=>6,'status'=>'active','created_at'=>date('c')],
        ],
        'reservations' => []
    ];
}
function load_store(): array {
    if (!file_exists(RMS_DB)) {
        $store=default_store(); save_store($store); return $store;
    }
    $data=json_decode((string)file_get_contents(RMS_DB), true);
    if (!is_array($data)) { $data=default_store(); save_store($data); }
    return $data;
}
function save_store(array $store): void {
    if (!is_dir(dirname(RMS_DB))) mkdir(dirname(RMS_DB), 0755, true);
    $tmp=RMS_DB.'.tmp.'.bin2hex(random_bytes(3));
    file_put_contents($tmp, json_encode($store, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmp, RMS_DB);
}
function with_store_lock(callable $fn): mixed {
    if (!is_dir(dirname(RMS_LOCK))) mkdir(dirname(RMS_LOCK),0755,true);
    $fp=fopen(RMS_LOCK,'c');
    if (!$fp) throw new RuntimeException('Could not open datastore lock.');
    flock($fp, LOCK_EX);
    try { return $fn(); } finally { flock($fp, LOCK_UN); fclose($fp); }
}
function find_property(array $store,int $id): ?array {
    foreach($store['properties'] as $p) if((int)$p['id']===$id) return $p;
    return null;
}
function overlap_exists(array $store,int $propertyId,string $in,string $out,?int $ignoreId=null): bool {
    foreach($store['reservations'] as $r){
        if((int)$r['property_id']!==$propertyId) continue;
        if($ignoreId!==null && (int)$r['id']===$ignoreId) continue;
        if(!in_array($r['status'],['pending','confirmed','checked_in'],true)) continue;
        if($r['check_in'] < $out && $r['check_out'] > $in) return true;
    }
    return false;
}
