Set fso = CreateObject("Scripting.FileSystemObject")
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
projectDir = fso.GetParentFolderName(scriptDir)

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = projectDir

phpExe = "php"
If Not (WshShell.Run("where php", 0, True) = 0) Then
    If fso.FileExists("C:\xampp\php\php.exe") Then
        phpExe = "C:\xampp\php\php.exe"
    End If
End If

WshShell.Run """" & phpExe & """ -S 0.0.0.0:8080 router.php", 0, False
