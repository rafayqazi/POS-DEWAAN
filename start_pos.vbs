Set WshShell = CreateObject("WScript.Shell")
Set objWMIService = GetObject("winmgmts:\\.\root\cimv2")

' Check if Apache is running
Set colApache = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = 'httpd.exe'")
If colApache.Count = 0 Then
    ' Start Apache directly
    WshShell.Run """C:\xampp\apache\bin\httpd.exe""", 0, False
    WScript.Sleep 4000
End If

' Check if MySQL is running
Set colMySQL = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = 'mysqld.exe'")
If colMySQL.Count = 0 Then
    ' Start MySQL directly
    WshShell.Run """C:\xampp\mysql\bin\mysqld.exe"" --defaults-file=""C:\xampp\mysql\bin\my.ini"" --standalone", 0, False
    WScript.Sleep 4000
End If

' Wait for services to fully initialize
WScript.Sleep 3000

' Get the current script's directory and folder name
Dim fso, scriptPath, scriptDir, folderName
Set fso = CreateObject("Scripting.FileSystemObject")
scriptPath = WScript.ScriptFullName
scriptDir = fso.GetParentFolderName(scriptPath)
folderName = fso.GetBaseName(scriptDir)

' Find Chrome path
Dim chromePath
chromePath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
If Not CreateObject("Scripting.FileSystemObject").FileExists(chromePath) Then
    chromePath = "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
End If

' Open the application in Chrome app mode
WshShell.Run """" & chromePath & """ --app=http://localhost/" & folderName & "/login.php --start-maximized", 1, False

' Wait for Chrome to start
WScript.Sleep 3000

' Start the monitoring script that will stop XAMPP when app closes
WshShell.Run "wscript.exe """ & scriptDir & "\stop_xampp_on_close.vbs""", 0, False

Set colApache = Nothing
Set colMySQL = Nothing
Set objWMIService = Nothing
Set WshShell = Nothing
