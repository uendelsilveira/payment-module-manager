# Contributing to Payment Module Manager

Thank you for considering contributing to the Payment Module Manager!

## Getting Started

1.  **Fork the repository** on GitHub.
2.  **Clone your fork** locally.
3.  **Install dependencies**:
    ```bash
    composer install
    ```
4.  **Set up the environment**:
    Copy `.env.example` to `.env` and configure your database and gateway credentials.

## Development Workflow

1.  **Create a branch** for your feature or bugfix:
    ```bash
    git checkout -b feat/my-new-feature
    ```
2.  **Write code** following the project's coding standards (PSR-12).
3.  **Run static analysis** to ensure code quality:
    ```bash
    composer phpstan
    composer rector
    composer lint
    ```
4.  **Run tests** to ensure no regressions:
    ```bash
    composer test
    ```

## Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification.

-   `feat:` New feature
-   `fix:` Bug fix
-   `docs:` Documentation changes
-   `style:` Formatting, missing semi-colons, etc.
-   `refactor:` Code refactoring
-   `test:` Adding missing tests
-   `chore:` Maintenance tasks

## Pull Requests

1.  Push your branch to your fork.
2.  Open a Pull Request against the `main` branch.
3.  Provide a clear description of your changes.
4.  Ensure all CI checks pass.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
