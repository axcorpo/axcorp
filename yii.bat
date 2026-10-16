@echo off

rem -------------------------------------------------------------
rem  Yii command line bootstrap script for Windows.
rem
rem  @author Qiang Xue <qiang.xue@gmail.com>
rem  @link http://www.yiiframework.com/
rem  @copyright Copyright (c) 2008 Yii Software LLC
rem  @license http://www.yiiframework.com/license/
rem -------------------------------------------------------------

@setlocal

set YII_PATH=%~dp0

rem The app needs PHP 8+ (ramsey/uuid); prefer Laragon's 8.3 build over the PATH default.
if "%PHP_COMMAND%" == "" if exist "C:\laragon\bin\php\php-8.3.25-nts-Win32-vs16-x64\php.exe" set "PHP_COMMAND=C:\laragon\bin\php\php-8.3.25-nts-Win32-vs16-x64\php.exe"
if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%YII_PATH%yii" %*

@endlocal
