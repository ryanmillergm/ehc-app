# The EHC App

This app was developed to allow an organization to showcase their website with the ability for administrators to manage everything through an intuitive Content Management System (cms). Administrators are able to manage things such as Events, donors/sponsors, with the ability to  separate out manageable resource into departments using Filament's Multi-Tenancy. All resources are managed policies that only allow access by users with assigned roles with given permissions from the super admin.

## Roles and permissions
A role can have many permissions. A role or individual permissions can be assigned to a user. Permissions give users access to resources in the admin panels. A user can have many permissions through a role.
The access to different models is controlled through each models policy. Ex/ `app\Policies\ChildPolicy.php`

# Access Admin Panel
The Admin Panel has access to all resources. A user must have the permission or a role with the permission `admin.panel` in order to have access. A user must also have permissions or a role with permissions for the resources they can access.
`/admin`

# Access Teams Admin Panel
The Teams Admin Panel has access to limited resources only. A user must have the permission or a role with the permission `org.panel` in order to have access. A user must also have permissions or a role with permissions for the resources they can access.
`/org/{team_name}` 

# Pages with Translations
This has pages by language translations. A page has many translations and a translation belongs to a page. Defaults to english and finds translation by selected language.


## Tech Stack
 - Framework: Laravel v11.5.0
 - Language: PHP v8.2.18
 - Database: MySQL v8.0.23
 - Livewire v3.4.11
 - Jetstream v5.0.4 
 - ORM: Eloquent
 - Testing: PHPUnit v11.1.3
 - Testing: Laravel Dusk v8.2.0 
 - Node v16.20.2

 ## Other Packages
 - CMS: Filament v3.2.71
 - Spatie laravel-permission v6.7.0
 - `$ composer show` to view all installed packages and their current version

## Local Setup
 - `$ git clone https://github.com/ryanmillergm/ehc-app.git`
 - `$ cd ehc-app`
 - `$ composer install`
 - `$ npm install && npm run dev`
 - Create your local database
 - Copy env.testing to .env and fill in your environment
 - `$ php artisan migrate`
 - `$ php artisan db:seed`
 - `$ npx sequelize db:migrate`

 ## Running the Server Locally
 - `$ php artisan serve`
 - Access local endpoints at `http://127.0.0.1:8000`
 
 ## Setup stripe
 - `stripe login`
 - `stripe listen --forward-to http://bread-of-grace-ministries.test/stripe/webhook`
 or
 - `stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook`

 ## All in one command - Run Vite Build, php server and stripe listen --forward-to,
 - `$ composer dev` 

## Running the Test Suite
 - `php artisan test`

 ## Core Contributors
 - Ryan Miller, [@ryanmillergm](https://github.com/ryanmillergm)

## Current Iterations

## Future Iterations

## Known Issues
 - None

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# ehc-app
