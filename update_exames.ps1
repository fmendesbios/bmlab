
$examesPath = "d:\xampp\htdocs\bmlab\exames.php"
$editorPath = "d:\xampp\htdocs\bmlab\editor_laudo.txt"

$examesContent = Get-Content -Path $examesPath -Raw -Encoding UTF8
$editorContent = Get-Content -Path $editorPath -Raw -Encoding UTF8

# 1. Prepare Editor Content (Remove "HTML" line if present)
if ($editorContent.StartsWith("HTML")) {
    $firstLineBreak = $editorContent.IndexOf("`n")
    if ($firstLineBreak -ge 0) {
        $editorContent = $editorContent.Substring($firstLineBreak + 1)
    }
}

# 2. Remove PHP Tabulator Block
$startMarkerPhp = "// ---------- DADOS PARA O TABULATOR (GRID DO LAUDO) ----------"
$endMarkerPhp = "// ---------- CARREGAR CONFIGURAÃ‡Ã•ES DE COLUNAS/CÃ‰LULAS DO BANCO ----------"

$startIdx = $examesContent.IndexOf($startMarkerPhp)
$endIdx = $examesContent.IndexOf($endMarkerPhp)

if ($startIdx -ge 0 -and $endIdx -ge 0 -and $startIdx -lt $endIdx) {
    $examesContent = $examesContent.Substring(0, $startIdx) + $examesContent.Substring($endIdx)
    Write-Host "Removed PHP Tabulator block."
} else {
    Write-Host "PHP Tabulator markers not found or invalid."
}

# 3. Remove Old JS Block
$startMarkerJs = "// ------------------- EDITOR DO LAUDO (SEÃ‡Ã•ES / LINHAS / COLUNAS, SEM TABULATOR) -------------------"
$endMarkerJsContent = "document.getElementById('celulas_cfg_json').value = JSON.stringify(celulas_cfg_json);"

$startIdx = $examesContent.IndexOf($startMarkerJs)

if ($startIdx -ge 0) {
    $contentEndIdx = $examesContent.IndexOf($endMarkerJsContent, $startIdx)
    if ($contentEndIdx -ge 0) {
        $closingBraceIdx = $examesContent.IndexOf("});", $contentEndIdx)
        if ($closingBraceIdx -ge 0) {
            # Preserve the });
            $examesContent = $examesContent.Substring(0, $startIdx) + "`n/* Old Laudo Code Removed */`n" + $examesContent.Substring($closingBraceIdx)
            Write-Host "Removed Old JS block."
        } else {
            Write-Host "Could not find closing brace for JS block."
        }
    } else {
        Write-Host "Could not find end content marker for JS."
    }
} else {
    Write-Host "Old JS start marker not found."
}

# 4. Replace Aba-Laudo Content
$abaLaudoStart = '<div class="tab-pane fade" id="aba-laudo" role="tabpanel" aria-labelledby="aba-laudo-tab">'
$abaValoresMarker = 'id="aba-valores"'

$startIdx = $examesContent.IndexOf($abaLaudoStart)
$endIdx = $examesContent.IndexOf($abaValoresMarker)

if ($startIdx -ge 0 -and $endIdx -ge 0) {
    # Find the start of the aba-valores div tag (searching backwards from endIdx)
    $divStart = $examesContent.LastIndexOf("<div", $endIdx)
    
    if ($divStart -gt $startIdx) {
        $newBlock = $abaLaudoStart + "`n" + $editorContent + "`n</div>`n`n"
        $examesContent = $examesContent.Substring(0, $startIdx) + $newBlock + $examesContent.Substring($divStart)
        Write-Host "Replaced Aba-Laudo content."
    } else {
        Write-Host "Could not locate start of aba-valores div correctly."
    }
} else {
    Write-Host "Aba-Laudo markers not found."
}

$examesContent | Set-Content -Path $examesPath -Encoding UTF8
