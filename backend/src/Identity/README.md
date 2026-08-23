# Identity Context

Accounts, credentials and roles.

## Ubiquitous language

- **User**: account with email, password hash and roles (`ROLE_ADMIN` backoffice,
  `ROLE_PLAYER` in-game) (FR-030).
- Registration yields ROLE_PLAYER; admins are provisioned via `app:create-admin`.

## Dependency rule

Domain = pure PHP 8.3. Application owns `UserRepositoryInterface`. Infrastructure provides Doctrine
persistence, JWT integration and auth controllers (Constitution I–II).
