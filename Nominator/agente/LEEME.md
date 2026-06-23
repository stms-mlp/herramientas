# Agente de reporte de hardware

`Reportar-Hardware.bat` — script de **doble clic** para que cualquier usuario
(aunque no tenga conocimientos técnicos) releve el hardware de su PC y lo envíe
al inventario Nominator.

## Cómo funciona

1. El usuario hace **doble clic** en `Reportar-Hardware.bat`.
2. El script usa herramientas internas de Windows (CIM/WMI) para leer CPU,
   motherboard, memoria, discos y placa de video. **No instala nada.**
3. Arma un reporte en un formato que Nominator entiende y lo **sube por HTTPS**
   al sistema (con un token). No se exponen contraseñas en el archivo.
4. En Nominator, el reporte aparece en **Reportes** (bandeja); el técnico elige
   área y tipo y crea el equipo con sus componentes en un clic.

Si el envío falla (sin red, etc.), guarda una copia en `%TEMP%` y le pide al
usuario que la envíe manualmente.

## Configuración (antes de distribuirlo)

Editar las dos líneas de configuración dentro del `.bat`:

```powershell
$NominatorUrl = 'https://TU-DOMINIO/Nominator/index.php?r=agente.recibir'
$Token        = 'CAMBIAR-ESTE-TOKEN-1234'
```

- `$NominatorUrl`: la URL pública de tu Nominator (la ruta `agente.recibir`).
- `$Token`: debe ser **igual** a `AGENTE_TOKEN` en `lib/config.php`. Cambialo
  por un valor propio en ambos lados.

## Seguridad

- El token sólo autoriza a **dejar** un reporte en la bandeja; no da acceso al
  sistema ni a datos.
- Conviene servir Nominator por **HTTPS** para que el reporte viaje cifrado.
- Los reportes quedan **pendientes**: nada entra al inventario hasta que un
  técnico lo revisa y lo confirma.

## Compatibilidad

- Windows 8/10/11 y Server (PowerShell 5.1 en adelante).
- En equipos viejos sin el módulo de almacenamiento, cae a `Win32_DiskDrive`.
