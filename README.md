# Cinema Booking API

REST API application for cinema seat reservations built with Symfony 6.4.

## Requirements

- Docker and Docker Compose
- Git

## Quick Start

### 1. Clone and start containers

```bash
git clone <repository-url>
cd rekrutacja
docker-compose up -d --build
```

### 2. Install dependencies

```bash
docker-compose exec php composer install
```

### 3. Generate JWT keys

```bash
docker-compose exec php mkdir -p config/jwt
docker-compose exec php openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:cinema_jwt_passphrase
docker-compose exec php openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:cinema_jwt_passphrase
```

### 4. Run migrations and load fixtures

```bash
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec php bin/console doctrine:fixtures:load --no-interaction
```

### 5. Access the API

The API is available at: `http://localhost:8080`

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/rooms` | List available rooms |
| GET | `/api/screenings` | List upcoming screenings |
| GET | `/api/screenings/{id}` | Get screening details with seat availability |
| POST | `/api/reservations` | Create a new reservation |

### Admin Endpoints (JWT Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Authenticate and get JWT token |
| GET | `/api/admin/rooms` | List all rooms |
| POST | `/api/admin/rooms` | Create new room |
| PUT | `/api/admin/rooms/{id}` | Update room |
| DELETE | `/api/admin/rooms/{id}` | Delete room |

## Usage Examples

### Create Reservation

```bash
curl -X POST http://localhost:8080/api/reservations \
  -H "Content-Type: application/json" \
  -d '{
    "screeningId": 1,
    "seats": [1, 2, 3],
    "customerName": "Jan Kowalski",
    "customerEmail": "jan@example.com"
  }'
```

### Admin Login

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@cinema.pl",
    "password": "admin123"
  }'
```

### Create Room (Admin)

```bash
curl -X POST http://localhost:8080/api/admin/rooms \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <your-jwt-token>" \
  -d '{
    "name": "New Room",
    "rows": 10,
    "seatsPerRow": 15
  }'
```

## Running Tests

### All tests

```bash
docker-compose exec php bin/phpunit
```

### Unit tests only

```bash
docker-compose exec php bin/phpunit tests/Unit
```

### Integration tests only

```bash
docker-compose exec php bin/phpunit tests/Integration
```

## Project Structure

```
├── config/                 # Symfony configuration
├── docker/                 # Docker configuration files
│   ├── nginx/             # Nginx config
│   └── php/               # PHP Dockerfile
├── migrations/            # Database migrations
├── public/                # Web entry point
├── src/
│   ├── Controller/        # API controllers
│   │   └── Api/
│   │       ├── Admin/     # Admin endpoints (JWT protected)
│   │       ├── PublicController.php
│   │       └── ReservationController.php
│   ├── DataFixtures/      # Test data
│   ├── Entity/            # Doctrine entities
│   ├── Exception/         # Custom exceptions
│   ├── Repository/        # Doctrine repositories
│   └── Service/           # Business logic
├── tests/
│   ├── Integration/       # Integration tests
│   └── Unit/              # Unit tests
├── docker-compose.yml
└── README.md
```

## Technologies

- PHP 8.2
- Symfony 6.4
- Doctrine ORM
- MySQL 8.0
- JWT Authentication (LexikJWTAuthenticationBundle)
- PHPUnit 10
- Docker

## Default Admin Credentials

- Email: `admin@cinema.pl`
- Password: `admin123`
