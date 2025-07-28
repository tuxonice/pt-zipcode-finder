# Contributing Guide

Thanks for considering contributing! This guide explains how to set up the project, run checks, and submit changes.

## Getting started

- Requirements: PHP 8.3+, Composer, Docker (optional but recommended)
- Install dependencies:
  - `composer install` or `docker-compose run --rm app composer install`
- Run tests:
  - `vendor/bin/phpunit` or `docker-compose run --rm app vendor/bin/phpunit`

## Development workflow

- Lint (PSR-12):
  - `vendor/bin/phpcs` or `docker-compose run --rm app vendor/bin/phpcs`
- Static analysis:
  - `vendor/bin/phpstan` or `docker-compose run --rm app vendor/bin/phpstan`
- Run importer locally (example):
  - `php bin/zipcode-importer import data data --dbname=zipcodes`
  - Or with Docker: `docker-compose run --rm app php bin/zipcode-importer import /app/data /app/data --dbname=zipcodes`

## Pull requests

- Write tests for new features and bug fixes.
- Ensure `phpunit`, `phpcs`, and `phpstan` pass.
- Update README if behavior/usage changes.
- Keep PRs focused and small when possible; include a clear description and rationale.

## Commit style

- Use clear, descriptive messages. Reference issues when applicable (e.g., `Fixes #123`).

## Reporting issues

- Use the Bug Report template. Provide steps to reproduce, expected vs actual behavior, and environment details.
