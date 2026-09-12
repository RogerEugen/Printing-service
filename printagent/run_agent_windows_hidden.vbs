Option Explicit

Dim fileSystem, shell, agentDirectory, pythonExecutable, agentScript, command, exitCode

Set fileSystem = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

agentDirectory = fileSystem.GetParentFolderName(WScript.ScriptFullName)
pythonExecutable = fileSystem.BuildPath(agentDirectory, "venv\Scripts\python.exe")
agentScript = fileSystem.BuildPath(agentDirectory, "agent.py")

If Not fileSystem.FileExists(pythonExecutable) Then
    WScript.Quit 2
End If

shell.CurrentDirectory = agentDirectory
command = Chr(34) & pythonExecutable & Chr(34) & " " & Chr(34) & agentScript & Chr(34)

' Window style 0 keeps the worker hidden. Waiting keeps Task Scheduler's state
' accurate and lets it restart the task if the Python process exits unexpectedly.
exitCode = shell.Run(command, 0, True)
WScript.Quit exitCode
