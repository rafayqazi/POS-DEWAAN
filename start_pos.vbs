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

' Get the current script's directory and folder name relative to htdocs
Dim fso, scriptPath, scriptDir, folderName, pos, relPath
Set fso = CreateObject("Scripting.FileSystemObject")
scriptPath = WScript.ScriptFullName
scriptDir = fso.GetParentFolderName(scriptPath)

pos = InStr(1, scriptDir, "\htdocs\", vbTextCompare)
If pos > 0 Then
    relPath = Mid(scriptDir, pos + 8)
    folderName = Replace(relPath, "\", "/")
Else
    folderName = fso.GetFolder(scriptDir).Name
End If

' Create/Update Desktop Shortcut
Dim desktopPath, shortcut
desktopPath = WshShell.SpecialFolders("Desktop")
Set shortcut = WshShell.CreateShortcut(desktopPath & "\Fashion Shines POS.lnk")
shortcut.TargetPath = "wscript.exe"
shortcut.Arguments = """" & scriptPath & """"
shortcut.WorkingDirectory = scriptDir
shortcut.Description = "Launch Fashion Shines POS System"

' Set the icon - checking for custom ico file
Dim iconPath
iconPath = scriptDir & "\assets\img\logo.ico"
If Not fso.FileExists(iconPath) Then
    iconPath = scriptDir & "\assets\img\favicon.ico"
End If

If fso.FileExists(iconPath) Then
    shortcut.IconLocation = iconPath
Else
    ' Fallback to a professional system icon if custom ico is missing
    shortcut.IconLocation = "imageres.dll, 185" 
End If

shortcut.Save

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
