Set WshShell = CreateObject("WScript.Shell")
Set objWMIService = GetObject("winmgmts:\\.\root\cimv2")

' Get the name of current directory relative to htdocs
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

' Monitor Chrome process for POS application
Do
    WScript.Sleep 2000 ' Check every 2 seconds
    
    ' Check if Chrome with our app is running
    Set colProcesses = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = 'chrome.exe' AND CommandLine LIKE '%localhost/" & folderName & "/%'")
    
    If colProcesses.Count = 0 Then
        ' Chrome app is closed, stop XAMPP services using Control Panel
        WshShell.Run "C:\xampp\xampp-control.exe -stop apache", 0, True
        WshShell.Run "C:\xampp\xampp-control.exe -stop mysql", 0, True
        
        ' Wait a moment for graceful shutdown
        WScript.Sleep 2000
        
        ' Force kill if still running
        WshShell.Run "taskkill /F /IM httpd.exe", 0, True
        WshShell.Run "taskkill /F /IM mysqld.exe", 0, True
        
        Exit Do
    End If
Loop

Set WshShell = Nothing
Set objWMIService = Nothing
