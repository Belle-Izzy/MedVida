# MedVida API

Backend REST do MedVida, uma plataforma de agendamento médico com suporte a clínicas, pacientes, médicos, disponibilidades e consultas.

## Tecnologias

- PHP 8.3+
- Laravel 13
- PostgreSQL 18
- Laravel Sanctum para autenticação por token

## Configuração local

1. Copie `.env.example` para `.env` e preencha as variáveis do PostgreSQL.
2. Gere a chave da aplicação com `php artisan key:generate`.
3. Execute as migrations com `php artisan migrate`.
4. Opcionalmente, carregue dados de desenvolvimento com `php artisan db:seed --class=MedVidaSeeder`.
5. Inicie o servidor com `php artisan serve --host=127.0.0.1 --port=8001`.

O servidor ficará disponível em `http://127.0.0.1:8001`.

## Rotas principais

- `POST /api/auth/login`
- `POST /api/clinicas`
- `POST /api/pacientes`
- `GET /api/medicos`
- `GET /api/disponibilidades`
- `GET /api/consultas`
- `POST /api/consultas`

As rotas privadas utilizam `Authorization: Bearer <token>`. Pacientes acessam apenas seus dados e consultas; clínicas administram exclusivamente médicos, disponibilidades e consultas associados à própria clínica.

## Regras de agendamento

- Uma consulta só pode ser criada em horário disponível.
- A reserva ocorre em transação no banco, evitando conflito entre requisições simultâneas.
- Recusa ou cancelamento devolve o horário à disponibilidade.
- Um horário ativo não pode ter mais de uma consulta pendente ou confirmada.
