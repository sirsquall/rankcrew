# RankCrew Laravel Client

Official Laravel integration for [RankCrew.ai](https://rankcrew.ai).

## Installation

1.  Require the package:
    ```bash
    composer require sirsquall/rankcrew
    ```

3.  Run migrations:
    ```bash
    php artisan migrate
    ```

## Configuration

The package automatically registers its routes and service provider.

### Routes provided:
-   `POST /rankcrew/login`: Authenticate via RankCrew.
-   `GET /session/token`: Retrieve CSRF token.
-   `POST /api/rankcrew`: Create blog posts.
-   `GET /api/rankcrew/categories`: List categories.

## Usage

This package is designed to work out-of-the-box with the RankCrew SaaS platform. Authenticate your website in the RankCrew dashboard using your admin credentials.
