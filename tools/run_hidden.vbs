Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "E:\php projects\SendMessageBots\"
WshShell.Run """C:\xampp\php\php.exe"" -S 0.0.0.0:8080 router.php", 0, False
