# Frontend do MedVida

Esta pasta guarda o código-fonte da interface. Edite estes arquivos para mudar o aplicativo:

| Arquivo | O que você encontra |
| --- | --- |
| `views/welcome.blade.php` | Estrutura HTML inicial da aplicação. |
| `css/app.css` | Cores, tamanhos, cartões, botões, telas mobile e responsividade. |
| `js/app.js` | Telas, login, cadastro, sessão, consulta de dados e ações dos botões. |

## Onde fazer mudanças pequenas

- Textos e títulos: procure a função da tela em `js/app.js` (por exemplo, `home`, `clinic`, `medics` ou `register`).
- Cores e aparência: altere as variáveis no início de `css/app.css`.
- Logo: `public/logo-medvida.png`.

## Arquivos que precisam ficar em `public/`

`public/` contém somente arquivos que o navegador acessa diretamente: a logo, o ícone, o manifesto e o service worker da PWA. Eles não devem ir para `resources/`, pois o navegador não conseguiria carregá-los no celular.

As rotas que disponibilizam os arquivos de `resources/` localmente ficam em `routes/web.php`.
