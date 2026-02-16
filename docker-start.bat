@echo off
echo ========================================
echo   GatewayPro - Docker Setup
echo ========================================
echo.

REM Verificar se config.php existe com configuracoes Docker
echo [1/4] Configurando config.php para Docker...
copy /Y config\config.docker.php config\config.php
echo Config atualizado!
echo.

REM Criar pasta uploads se nao existir
echo [2/4] Verificando pasta uploads...
if not exist "uploads" mkdir uploads
if not exist "uploads\config" mkdir uploads\config
if not exist "uploads\aula_files" mkdir uploads\aula_files
echo Pastas verificadas!
echo.

REM Subir containers
echo [3/4] Iniciando containers Docker...
docker-compose up -d --build
echo.

echo [4/4] Aguardando banco de dados inicializar...
timeout /t 15 /nobreak > nul
echo.

echo ========================================
echo   Setup concluido!
echo ========================================
echo.
echo   Aplicacao: http://localhost:8082
echo   phpMyAdmin: http://localhost:8081
echo.
echo   Login Admin:
echo   Email: admin@gmail.com
echo   Senha: admin123
echo.
echo   Para parar: docker-compose down
echo ========================================
pause
