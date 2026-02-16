@echo off
echo Parando containers GatewayPro...
docker-compose down
echo.
echo Containers parados!
echo.
echo Para remover volumes (apagar banco): docker-compose down -v
pause
