@echo off
rem Runs the PHPUnit suite for the master app, using the repo-pinned PHPUnit 9
rem PHAR (tools\phpunit-9.phar); arguments are passed explicitly so no phpunit.xml
rem is needed.
rem
rem The suite needs the master_test database (schema only):
rem   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS master_test CHARACTER SET utf8 COLLATE utf8_unicode_ci"
rem   mysql -u root -p master_test < tests\_schema.sql

set PHP=C:\laragon\bin\php\php-8.3.25-nts-Win32-vs16-x64\php.exe
if not exist "%PHP%" set PHP=php
"%PHP%" "%~dp0..\tools\phpunit-9.phar" --bootstrap "%~dp0tests\bootstrap.php" --colors=always "%~dp0tests\unit" %*
