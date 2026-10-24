$ErrorActionPreference = 'Stop'
$docPath = 'D:\laragon\www\BARANGAY LUNA SYSTEM\research-output\BARANGAY_LUNA_CHAPTERS_1_TO_5_ORIGINAL_WORDING.docx'
$pdfPath = 'D:\laragon\www\BARANGAY LUNA SYSTEM\research-output\work\approved-wording-review.pdf'
$wordApp = New-Object -ComObject Word.Application
$wordApp.Visible = $false
$wordApp.DisplayAlerts = 0
try {
    $manuscript = $wordApp.Documents.Open($docPath, $false, $false)
    $manuscript.Repaginate()
    $manuscript.Fields.Update() | Out-Null
    $manuscript.Repaginate()
    $manuscript.Fields.Update() | Out-Null
    $manuscript.Save()
    $manuscript.ExportAsFixedFormat($pdfPath, 17)
    $manuscript.Close(0)
    'Word pagination and PDF export succeeded'
} finally {
    $wordApp.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($wordApp) | Out-Null
}

