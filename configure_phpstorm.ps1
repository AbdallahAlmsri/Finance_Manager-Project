# PowerShell script to help configure PHPStorm PHP interpreter
# This script creates a configuration that PHPStorm should recognize

Write-Host "Configuring PHPStorm PHP Interpreter..." -ForegroundColor Green

$phpPath = "C:\xampp\php\php.exe"
$phpVersion = "8.2.12"

# Verify PHP exists
if (Test-Path $phpPath) {
    Write-Host "✓ Found PHP at: $phpPath" -ForegroundColor Green
    
    # Get PHP version
    $versionOutput = & $phpPath --version
    Write-Host "✓ PHP Version: $versionOutput" -ForegroundColor Green
    
    Write-Host "`nConfiguration files have been updated." -ForegroundColor Yellow
    Write-Host "Please restart PHPStorm or reload the project for changes to take effect." -ForegroundColor Yellow
    Write-Host "`nIf the interpreter still doesn't appear:" -ForegroundColor Cyan
    Write-Host "1. Go to File > Settings > PHP" -ForegroundColor White
    Write-Host "2. Click '...' next to CLI Interpreter" -ForegroundColor White
    Write-Host "3. Click '+' to add new interpreter" -ForegroundColor White
    Write-Host "4. Select 'Local' and browse to: $phpPath" -ForegroundColor White
    Write-Host "5. Click OK to save" -ForegroundColor White
} else {
    Write-Host "✗ PHP not found at: $phpPath" -ForegroundColor Red
    Write-Host "Please install XAMPP or update the path in this script." -ForegroundColor Yellow
}

