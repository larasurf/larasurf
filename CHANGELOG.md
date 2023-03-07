# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-beta.3] - 2023-03-07

### Fixed

- Issue where LocalStack CloudFormation template in CircleCI templates used outdated path
- Issue where uploading to S3 via Dusk in CI/CD could cause CORS errors

## [1.0.0-beta.2] - 2023-02-24

### Added

- Make `surf build` synonymous with `surf rebuild`
- Change `surf fresh` command to allow post-migrations scripts to finish before seeding (when `--seed` flag is given)

### Fixed

- Issue where updated version of dusk couldn't run tests on CircleCI 

## [1.0.0-beta.1] - 2023-02-20

### Security

- Within CI/CD, filter environment variables to those prefixed with `VITE_` to avoid baking secrets into image layer

## [1.0.0-beta] - 2023-02-20

### Added

- Logging to `laravel.log` in the event CloudFormation stack status can't be fetched
- Added `surf pint` command

### Changed

- Update target Laravel version to 10
- Update PHP version to 8.1
- Replace MailHog with MailPit
- Support PHPUnit 10
- Default timeout for CircleCI deployment updated from 30m to 1.5h
- Moved `surf.sh` to `surf` within proper composer `bin` location

### Fixed

- Issue where environment variables weren't available at build time for public assets within CI/CD
- Local file permission issues with volumes on WSL2

### Removed

- PHP-CS-Fixer option/command removed in favor of Laravel Pint
