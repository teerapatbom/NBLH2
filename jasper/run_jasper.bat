@echo off
set BASE=C:\xampp\htdocs\kpi_moph\jasper
set JAVA="C:\Program Files\Java\jre1.8.0_471\bin\java.exe"

%JAVA% ^
-cp "%BASE%\JasperStarter\lib\*;%BASE%\JasperStarter\lib\fonts" ^
de.cenote.jasperstarter.App %*
