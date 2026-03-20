function Validate-Controller {
    param([string]$Text)

    if ($Text -notmatch "Confirmed controller state") { throw "Missing section" }

    $PathPattern = "C:\\Users\\Steven\\contextkeeper-site\\\.contextkeeper\\[^\s]+"
    $Paths = [regex]::Matches($Text, $PathPattern) | ForEach-Object { $_.Value }

    foreach ($p in $Paths) {
        if (-not (Test-Path $p)) { throw "Invalid path: $p" }
    }

    return $true
}

# Existing logic runs here

# AFTER generating controller packet:
\ = Get-Content -Raw "C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-chat\START-CHAT-PACKET.md"

Validate-Controller \
