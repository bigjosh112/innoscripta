<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# HR Service

Laravel API for employee CRUD with event publishing to RabbitMQ (multi-country: USA & Germany).

---

## Quick start with Docker

Start everything (PostgreSQL, RabbitMQ, HR Service) with one command:

```bash
docker compose up -d
```

- **API:** http://localhost:8000  
- **RabbitMQ Management UI:** http://localhost:15672 (guest / guest)  
- **PostgreSQL:** localhost:5432, database `hr_service`, user `hr_user`, password `hr_secret`

The app runs migrations on startup. To run tests inside the container:

```bash
docker compose exec hr-service php artisan test
```

---

## Running locally (without Docker)

1. **Requirements:** PHP 8.2+, Composer, PostgreSQL (or SQLite), RabbitMQ (optional for publish).

2. **Install and configure:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   composer install
   ```
   In `.env` set DB_* and RABBITMQ_* (or use SQLite and skip RabbitMQ for local dev).

3. **Migrations:** `php artisan migrate`  
4. **Serve:** `php artisan serve` → API at http://localhost:8000

---

## Testing

**Automated tests (no RabbitMQ needed):**
```bash
php artisan test
# or: php artisan test tests/Feature/EmployeeApiTest.php
```

**Manual testing with cURL:**

- List: `curl -s http://localhost:8000/api/employees`
- List by country: `curl -s "http://localhost:8000/api/employees?country=USA"`
- Show: `curl -s http://localhost:8000/api/employees/1`
- Create USA: `curl -s -X POST http://localhost:8000/api/employees -H "Content-Type: application/json" -d '{"name":"John","last_name":"Doe","country":"USA","salary":75000,"ssn":"123-45-6789","address":"123 Main St"}'`
- Create Germany: `curl -s -X POST http://localhost:8000/api/employees -H "Content-Type: application/json" -d '{"name":"Hans","last_name":"Mueller","country":"Germany","salary":65000,"goal":"Increase productivity","tax_id":"DE123456789"}'`
- Update: `curl -s -X PUT http://localhost:8000/api/employees/1 -H "Content-Type: application/json" -d '{"salary":80000}'`
- Delete: `curl -s -X DELETE http://localhost:8000/api/employees/1`

When RabbitMQ is running, create/update/delete publish events to exchange `hr.events` (routing keys e.g. `employee.created.USA`). Check in RabbitMQ Management UI: http://localhost:15672

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
