param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://')]
    [string]$PublicUrl,

    [string]$Database = 'medvida_apresentacao'
)

$projectDirectory = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectDirectory

$env:APP_ENV = 'academic'
$env:MEDVIDA_ACADEMIC_MODE = 'true'
$env:MEDVIDA_TESTE_LOCAL = 'false'
$env:APP_DEBUG = 'false'
$env:APP_URL = $PublicUrl.TrimEnd('/')
$env:DB_DATABASE = $Database

php artisan serve --host=127.0.0.1 --port=8002
