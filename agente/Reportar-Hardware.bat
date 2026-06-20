@echo off
rem ============================================================================
rem  Nominator - Reportar Hardware
rem  Departamento de Sistemas - Municipalidad de Lago Puelo
rem
rem  Doble clic para relevar el hardware de esta PC y enviarlo al inventario.
rem  No requiere instalar nada: usa herramientas internas de Windows.
rem ============================================================================
title Reportar Hardware - Departamento de Sistemas
echo.
echo   ============================================================
echo     NOMINATOR - Reporte de Hardware
echo     Departamento de Sistemas - Municipalidad de Lago Puelo
echo   ============================================================
echo.
echo   Relevando el hardware de esta PC y enviandolo al inventario.
echo   Aguarde unos segundos, por favor...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -Command "$c=Get-Content -LiteralPath '%~f0' -Raw; $m='#PS'+'START'; $i=$c.IndexOf($m); Invoke-Expression $c.Substring($i+$m.Length)"
echo.
pause
exit /b

#PSSTART
# ============================ PowerShell ====================================
$ErrorActionPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# --------------------------- CONFIGURACION ---------------------------------
# Editar estos dos valores antes de distribuir el archivo:
$NominatorUrl = 'https://TU-DOMINIO/Nominator/index.php?r=agente.recibir'
$Token        = 'CAMBIAR-ESTE-TOKEN-1234'
# ---------------------------------------------------------------------------

function KV($k, $v) { if ($null -ne $v -and "$v".Trim() -ne '') { "`t$k`t`t$v" } }
$sep = ('-' * 73)
$sb  = New-Object System.Collections.ArrayList
function Add($t) { [void]$sb.Add($t) }

# --- Identidad ---
$cs = Get-CimInstance Win32_ComputerSystem
$os = Get-CimInstance Win32_OperatingSystem
Add 'Nominator Hardware Agent Report'
Add ('=' * 73)
Add ''
Add 'System Information'
Add $sep
$usuario = if ($cs.UserName) { $cs.UserName } else { $env:USERNAME }
Add (KV 'Computer Name' $env:COMPUTERNAME)
Add (KV 'User'          $usuario)
Add (KV 'OS'            $os.Caption)
Add ''

# --- CPU ---
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
if ($cpu) {
    Add 'Processors Information'
    Add $sep
    Add ''
    Add 'Socket 1			ID = 0'
    Add (KV 'Manufacturer'      $cpu.Manufacturer)
    Add (KV 'Name'              $cpu.Name.Trim())
    Add (KV 'Number of cores'   $cpu.NumberOfCores)
    Add (KV 'Number of threads' $cpu.NumberOfLogicalProcessors)
    Add (KV 'Max Frequency'     ("{0} MHz" -f $cpu.MaxClockSpeed))
    Add ''
}

# --- DMI (Motherboard + RAM) ---
Add 'DMI'
Add $sep
Add ''
$bb = Get-CimInstance Win32_BaseBoard
if ($bb) {
    Add 'DMI Baseboard'
    Add (KV 'vendor' $bb.Manufacturer)
    Add (KV 'model'  $bb.Product)
    Add (KV 'serial' $bb.SerialNumber)
    Add ''
}
$tipoRam = @{ 20='DDR'; 21='DDR2'; 24='DDR3'; 26='DDR4'; 34='DDR5' }
foreach ($m in (Get-CimInstance Win32_PhysicalMemory)) {
    Add 'DMI Memory Device'
    Add (KV 'designation'   $m.DeviceLocator)
    Add (KV 'size'          ("{0} GB" -f [math]::Round($m.Capacity / 1GB)))
    Add (KV 'type'          $tipoRam[[int]$m.SMBIOSMemoryType])
    Add (KV 'manufacturer'  ($m.Manufacturer.Trim()))
    Add (KV 'part number'   ($m.PartNumber.Trim()))
    Add (KV 'serial number' ($m.SerialNumber.Trim()))
    Add (KV 'speed'         ("{0} MHz" -f $m.Speed))
    Add ''
}

# --- Discos ---
Add 'Storage'
Add $sep
Add ''
$idx = 0
$discos = Get-PhysicalDisk
if ($discos) {
    foreach ($d in $discos) {
        Add ("Drive`t{0}" -f $idx); $idx++
        Add (KV 'Name'     $d.FriendlyName)
        Add (KV 'Serial'   $d.SerialNumber)
        Add (KV 'Capacity' ("{0} GB" -f [math]::Round($d.Size / 1GB, 1)))
        Add (KV 'Type'     ("Fixed, {0}" -f $d.MediaType))
        Add (KV 'Bus Type' $d.BusType)
        Add ''
    }
} else {
    foreach ($d in (Get-CimInstance Win32_DiskDrive)) {
        Add ("Drive`t{0}" -f $idx); $idx++
        Add (KV 'Name'     $d.Model)
        Add (KV 'Serial'   ($d.SerialNumber).Trim())
        Add (KV 'Capacity' ("{0} GB" -f [math]::Round($d.Size / 1GB, 1)))
        Add (KV 'Type'     'Fixed, SSD')
        Add (KV 'Bus Type' $d.InterfaceType)
        Add ''
    }
}

# --- AnyDesk ID ---
# Se obtiene de forma automatica: primero por la linea de comandos de AnyDesk
# (--get-id) y, si falla, leyendo el archivo de configuracion system.conf.
$anydeskId = ''
$adExe = @(
    "${env:ProgramFiles(x86)}\AnyDesk\AnyDesk.exe",
    "$env:ProgramFiles\AnyDesk\AnyDesk.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $adExe) {
    $adExe = (Get-ChildItem -Path "$env:ProgramData\AnyDesk","$env:APPDATA" -Filter 'AnyDesk.exe' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1).FullName
}
if ($adExe) {
    try { $anydeskId = (& $adExe --get-id 2>$null | Select-Object -First 1) } catch {}
}
if (-not $anydeskId) {
    foreach ($conf in @("$env:ProgramData\AnyDesk\system.conf", "$env:APPDATA\AnyDesk\system.conf")) {
        if (Test-Path $conf) {
            $line = Select-String -Path $conf -Pattern 'ad.anynet.id' -SimpleMatch | Select-Object -First 1
            if ($line -and $line.Line -match '=\s*(\d+)') { $anydeskId = $matches[1]; break }
        }
    }
}
if ("$anydeskId".Trim()) {
    Add 'Anydesk'
    Add $sep
    Add (KV 'Anydesk ID' ("$anydeskId".Trim()))
    Add ''
}

# --- GPU ---
$gpu = Get-CimInstance Win32_VideoController | Select-Object -First 1
if ($gpu) {
    Add 'Display Adapters'
    Add $sep
    Add ''
    Add 'Display adapter 0 (primary)'
    Add (KV 'Name' $gpu.Name)
    if ($gpu.AdapterRAM -gt 0) {
        Add (KV 'Memory size' ("{0} GB" -f [math]::Round($gpu.AdapterRAM / 1GB)))
    }
    Add ''
}

$reporte = ($sb -join "`r`n")

# Guardar copia local (por las dudas)
$copia = Join-Path $env:TEMP ("Nominator_{0}.txt" -f $env:COMPUTERNAME)
Set-Content -LiteralPath $copia -Value $reporte -Encoding UTF8

# --- Enviar a Nominator ---
$uri = $NominatorUrl + '&token=' + [uri]::EscapeDataString($Token)
try {
    $r = Invoke-RestMethod -Uri $uri -Method Post -Body $reporte -ContentType 'text/plain; charset=utf-8' -TimeoutSec 30
    Write-Host ''
    Write-Host '  [OK] Reporte enviado correctamente. Muchas gracias!' -ForegroundColor Green
    Write-Host ("       $r")
} catch {
    Write-Host ''
    Write-Host '  [!] No se pudo enviar automaticamente.' -ForegroundColor Yellow
    Write-Host ("      Se guardo una copia en: $copia")
    Write-Host '      Por favor envie ese archivo al Departamento de Sistemas.'
    Write-Host ("      Detalle: " + $_.Exception.Message)
}
