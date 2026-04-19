
$examesPath = "d:\xampp\htdocs\bmlab\exames.php"
$editorPath = "d:\xampp\htdocs\bmlab\editor_laudo.txt"

$examesContent = Get-Content -Path $examesPath -Raw -Encoding UTF8
$editorContent = Get-Content -Path $editorPath -Raw -Encoding UTF8

Write-Host "Editor content length: $($editorContent.Length)"

# 1. Prepare Editor Content (Remove "HTML" line if present)
if ($editorContent.StartsWith("HTML")) {
    $firstLineBreak = $editorContent.IndexOf("`n")
    if ($firstLineBreak -ge 0) {
        $editorContent = $editorContent.Substring($firstLineBreak + 1)
    }
}

# 2. Remove PHP Tabulator Block
$startMarkerPhp = "// ---------- DADOS PARA O TABULATOR"
$endMarkerPhp = "// ---------- CARREGAR CONFIGURA"

$startIdx = $examesContent.IndexOf($startMarkerPhp)
$endIdx = $examesContent.IndexOf($endMarkerPhp)

if ($startIdx -ge 0 -and $endIdx -ge 0 -and $startIdx -lt $endIdx) {
    $examesContent = $examesContent.Substring(0, $startIdx) + $examesContent.Substring($endIdx)
    Write-Host "Removed PHP Tabulator block."
} else {
    Write-Host "PHP Tabulator markers not found or invalid."
}

# 3. Remove Old JS Block
# Using substring matching for reliability
$startMarkerJs = "// ------------------- EDITOR DO LAUDO"
# Finding the closing script tag after the start marker
$startIdx = $examesContent.IndexOf($startMarkerJs)

if ($startIdx -ge 0) {
    # Find the next </script>
    $scriptEndIdx = $examesContent.IndexOf("</script>", $startIdx)
    
    if ($scriptEndIdx -ge 0) {
        # Find the last }); before </script> to keep the script structure if needed?
        # The old block seems to be inside a $(document).ready() or similar?
        # Let's just remove from start marker to BEFORE the </script>.
        # And assuming the old code ends with `});` which closes the `window.onload` or similar wrapper?
        # Actually, looking at the code, it seems the code is just sequential statements.
        # But there is a `});` at the end.
        # If I remove up to `</script>`, I remove the `});`.
        # If the block was opened before my start marker, I break the file.
        # The block starts at line ~3978 with `bindAjaxForm`.
        # My marker is at ~3982.
        # So I am inside a block.
        # I should keep the closing `});`.
        
        $closingBraceIdx = $examesContent.LastIndexOf("});", $scriptEndIdx)
        if ($closingBraceIdx -gt $startIdx) {
            # Remove from start marker to closingBraceIdx (exclusive, keeping }); )
            $examesContent = $examesContent.Substring(0, $startIdx) + "`n/* Old Laudo Code Removed */`n" + $examesContent.Substring($closingBraceIdx)
            Write-Host "Removed Old JS block."
        } else {
            Write-Host "Could not find closing brace for JS block."
        }
    } else {
        Write-Host "Could not find script end tag."
    }
} else {
    Write-Host "Old JS start marker not found."
}

# 4. Replace Aba-Laudo Content
$abaLaudoStart = '<div class="tab-pane fade" id="aba-laudo"'
$abaValoresMarker = 'id="aba-valores"'

$startIdx = $examesContent.IndexOf($abaLaudoStart)
$endIdx = $examesContent.IndexOf($abaValoresMarker)

if ($startIdx -ge 0 -and $endIdx -ge 0) {
    # Find the start of the aba-valores div tag (searching backwards from endIdx)
    $divStart = $examesContent.LastIndexOf("<div", $endIdx)
    
    if ($divStart -gt $startIdx) {
        # Construct new block.
        # $abaLaudoStart only matched the first part. We need the full opening tag?
        # No, we can just replace from startIdx.
        # But we need to close the div?
        # `editor_laudo.txt` does not have the wrapper.
        # So we need to PUT the wrapper.
        
        # We need to find where the opening tag of aba-laudo ENDS.
        $tagCloseIdx = $examesContent.IndexOf(">", $startIdx)
        if ($tagCloseIdx -gt 0) {
            $fullOpeningTag = $examesContent.Substring($startIdx, $tagCloseIdx - $startIdx + 1)
            
            $newBlock = $fullOpeningTag + "`n" + $editorContent + "`n</div>`n`n"
            $examesContent = $examesContent.Substring(0, $startIdx) + $newBlock + $examesContent.Substring($divStart)
            Write-Host "Replaced Aba-Laudo content."
        } else {
            Write-Host "Could not find end of aba-laudo opening tag."
        }
    } else {
        Write-Host "Could not locate start of aba-valores div correctly."
    }
} else {
    Write-Host "Aba-Laudo markers not found."
}

$examesContent | Set-Content -Path $examesPath -Encoding UTF8
