# MedVida

PWA acadêmico de agendamento de consultas. Clínicas cadastram médicos e horários; pacientes solicitam consultas; clínicas confirmam ou recusam os pedidos. A interface funciona em Android e iPhone pelo navegador, com instalação na tela inicial.

## Tecnologias

- PHP 8.3+, Laravel 13 e PostgreSQL
- HTML, CSS e JavaScript em `resources/`
- Service Worker e manifesto PWA em `public/`
- Laravel Sanctum para autenticação da API

## Executar localmente

1. Instale PHP, Composer e PostgreSQL.
2. Execute `composer install`.
3. Copie `.env.example` para `.env` e configure o banco de dados.
4. Execute `php artisan key:generate` e `php artisan migrate`.
5. Inicie com `php artisan serve --host=127.0.0.1 --port=8001`.

Abra `http://127.0.0.1:8001` no próprio computador. A porta do PostgreSQL é configurada por `DB_PORT` e pode ser diferente da porta do aplicativo.

## Modo acadêmico

Use **somente dados fictícios**. Separe um banco PostgreSQL próprio para a apresentação e configure o processo do aplicativo com:

```dotenv
APP_ENV=academic
MEDVIDA_ACADEMIC_MODE=true
APP_DEBUG=false
DB_DATABASE=medvida_apresentacao
APP_URL=https://SEU-ENDERECO-HTTPS
```

Execute as migrations nesse banco antes de abrir o aplicativo. Nesse modo, pacientes entram sem confirmação por e-mail. Clínicas criam a conta, veem o plano e ativam o acesso pelo botão de apresentação, sem cobrança; depois entram pela tela de login. Nenhuma operação de pagamento é enviada ao Mercado Pago. O modo acadêmico não concede acesso gratuito se a aplicação for executada com `APP_ENV=production`.

O aplicativo precisa de HTTPS para instalação e notificações no celular. Para uma apresentação a partir do próprio PC, um Tailscale Funnel pode publicar apenas a porta local do servidor, sem instalar Tailscale nos celulares. O PC, o PostgreSQL e a conexão com a internet precisam permanecer ligados. O Funnel é público: qualquer pessoa com o link pode abrir o app e criar uma conta. Não reutilize um banco com dados pessoais.

Para reiniciar o servidor da apresentação após ligar o PC, execute no PowerShell `./scripts/start-academic.ps1 -PublicUrl 'https://SEU-ENDERECO-HTTPS'`. Ele usa a porta local 8002 e o banco `medvida_apresentacao`; mantenha a janela aberta. O Funnel precisa apontar para `http://127.0.0.1:8002`. Se as políticas do Windows impedirem a execução do script, defina as variáveis acima na sessão e execute `php artisan serve --host=127.0.0.1 --port=8002`.

No Android, abra o link no Chrome e escolha **Instalar app** no menu. No iPhone, abra no Safari e escolha **Compartilhar → Adicionar à Tela de Início**. A instalação é do PWA; não há APK ou IPA neste repositório.

## Estrutura

| Pasta | Conteúdo |
| --- | --- |
| `app/` | Regras de negócio, modelos, controladores e serviços |
| `database/migrations/` | Estrutura do PostgreSQL |
| `resources/css/` | Estilos da interface |
| `resources/js/` | Telas e interações |
| `resources/views/` | Página inicial |
| `public/` | Ícones, manifesto e Service Worker |
| `routes/` | Rotas web, API e agendador |
| `tests/` | Testes automatizados |

Consulte `resources/README.md` para localizar rapidamente textos, cores e componentes da interface.

## Testes e limites

Execute `php artisan test`. Os testes de banco usam SQLite em memória; neste Windows, o Device Guard pode bloquear o módulo SQLite mesmo com o arquivo presente. O PostgreSQL usado pelo aplicativo não depende desse módulo.

O cache permite abrir partes da interface sem conexão, mas novas consultas e confirmações dependem do servidor. Não use este protótipo para atendimento médico real. Integrações futuras de cobrança e confirmação de e-mail permanecem no código, porém não são utilizadas no modo acadêmico.
