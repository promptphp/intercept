<p align="center">
    <img src="docs/logo/intercept-banner-dark.jpg" alt="Intercept by PromptPHP" width="100%">
</p>

<p align="center">
    <a href="https://github.com/promptphp/intercept/actions"><img alt="Build Status"src="https://github.com/promptphp/intercept/actions/workflows/tests.yml/badge.svg"></a>
    <a href="https://packagist.org/packages/promptphp/intercept"><img src="https://img.shields.io/packagist/v/promptphp/intercept?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/promptphp/intercept"><img src="https://img.shields.io/packagist/dt/promptphp/intercept?style=flat-square" alt="Downloads"></a>
    <a href="https://github.com/promptphp/intercept/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/promptphp/intercept"></a>
    <a href="https://laravel-news.com/intercept-middleware-guardrails-for-laravel-ai-agents/"><img src="https://img.shields.io/badge/Featured%20in%20Laravel%20News-F9322C?style=flat-square&logo=laravel&logoColor=white" alt="Featured in Laravel News"></a>
    <a href="https://www.youtube.com/watch?v=vFIfnufTYGQ"><img src="https://img.shields.io/badge/Demo-%23FF0000.svg?style=flat-square&logo=YouTube&logoColor=white" alt="Demo Video"></a>
</p>

## Introduction

Intercept is a modular, drop-in collection of reusable AI agent middleware for the [Laravel AI SDK](https://github.com/laravel/ai) offering middleware across security, observability, performance and guidance. It works by siting between your AI agent and your AI provider providing guardrails to prompts before they reach the provider, just like a typical HTTP middleware that sits between the route and your app's business logic.

### Demo

Watch a quick demo of Intercept in action.

[![Intercept Demo](https://img.youtube.com/vi/vFIfnufTYGQ/0.jpg)](https://www.youtube.com/watch?v=vFIfnufTYGQ)

## Requirements

> [!Important]
> Requires PHP 8.3+ and `laravel/ai`.

## AI Agent Middleware collection

Documentation on the full suite of middleware is available on the [documentation site](https://intercept.promptphp.com). The [roadmap](ROADMAP.md) provides an overview of planned features, upcoming middleware, and the project's future direction, while the [changelog](CHANGELOG.md) documents notable changes between releases.

## Official Documentation

Official documentation can be found at [https://intercept.promptphp.com/](https://intercept.promptphp.com/) or the [docs](docs/) directory on GitHub.

## Code of Conduct

We follow the Laravel [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct). We expect you to abide by these guidelines as well.

## License

Intercept by PromptPHP is open-sourced software licensed under the [MIT license](LICENSE).

## Support

This library is created by [Victor Ukam](https://victorukam.com) with contributions from the [Open Source Community](https://github.com/promptphp/Intercept/graphs/contributors). If you've found this package useful, please consider [sponsoring this project](https://github.com/sponsors/veeqtoh). It will go a long way to help with maintenance.
