@echo off
TITLE PocketMine-MP server software for Minecraft: Bedrock Edition
cd /d "%~dp0"

set "PLUGIN_PATH=plugin_source"
set "PHP_BINARY="

where /q php.exe
if %ERRORLEVEL%==0 (
    set "PHP_BINARY=php"
)

if exist bin\php\php.exe (
    set "PHPRC="
    set "PHP_BINARY=bin\php\php.exe"
)

if "%PHP_BINARY%"=="" (
    echo Couldn't find a PHP binary in system PATH or "%~dp0bin\php"
    echo Please refer to the installation instructions at https://doc.pmmp.io/en/rtfd/installation.html
    pause
    exit /b 1
)

if exist PocketMine-MP.phar (
    set "POCKETMINE_FILE=PocketMine-MP.phar"
) else (
    echo PocketMine-MP.phar not found
    echo Downloads can be found at https://github.com/pmmp/PocketMine-MP/releases
    pause
    exit /b 1
)

if not exist plugins mkdir plugins

for /d %%D in ("%PLUGIN_PATH%\*") do (
    if exist "%%D\plugin.yml" (
        echo.
        echo Building plugin: %%~nxD

        if exist "%%D\composer.json" (
            %PHP_BINARY% -dphar.readonly=0 pharynx.phar -c -i "%%D" -p="plugins\%%~nxD.phar"
        ) else (
            %PHP_BINARY% -dphar.readonly=0 pharynx.phar -i "%%D" -p="plugins\%%~nxD.phar"
        )

        if errorlevel 1 (
            echo Failed to build plugin: %%~nxD
            pause
            exit /b 1
        )

        %PHP_BINARY% -dphar.readonly=0 bootstrap-plugin-dev.php "plugins\%%~nxD.phar"

        if errorlevel 1 (
            echo Failed to bootstrap plugin: %%~nxD
            pause
            exit /b 1
        )
    )
)

if exist bin\mintty.exe (
    start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o Font="Consolas" -o FontHeight=10 -o CursorType=0 -o CursorBlinks=1 -h error -t "PocketMine-MP" -i bin/pocketmine.ico -w max %PHP_BINARY% %POCKETMINE_FILE% --enable-ansi %*
) else (
    %PHP_BINARY% %POCKETMINE_FILE% %* || pause
)
```
