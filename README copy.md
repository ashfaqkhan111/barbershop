# Pemon Barbershop (PHP native + PostgreSQL)
1. `createdb pemon_barbershop && psql -d pemon_barbershop -f sql/schema.sql`
2. Edit user/password di `config/db.php` (butuh ekstensi `pdo_pgsql` aktif di php.ini)
3. `php -S localhost:8000` lalu buka http://localhost:8000
