# Product Parser

This project is designed to parse products from the website [auto.ria.com](https://auto.ria.com) and save them to a database and make product.csv in the projects directory. After parsing, the data is available through a REST API.

## Requirements

- PHP 8.0 or higher
- Composer
- Symfony 5.x
- MySQL

## Installation

1. Clone the repository:
   git clone https://github.com/protsykhome/product-parser.git
   cd product-parser
2.composer install
3. Configure the database connection in the .env file:
    DATABASE_URL="mysql://username:password@localhost:3306/db_name"
4.Create the database:
    php bin/console doctrine:database:create
5.Run migrations:
    php bin/console doctrine:migrations:migrate

## Usage

To run the parser, execute the following command:
php bin/console ParseAutoRia

The data obtained from parsing is available at the following endpoint:
GET /api/products







