# Configuración de Android - AutoService ITSM

Esta guía te ayudará a convertir la aplicación web en una app Android nativa usando Capacitor.

## Requisitos Previos

### 1. Instalar Android Studio
- Descargar desde: https://developer.android.com/studio
- Durante la instalación, asegúrate de incluir:
  - Android SDK
  - Android SDK Platform-Tools
  - Android Virtual Device (AVD)

### 2. Configurar Variables de Entorno (Windows)

```powershell
# Agregar a las variables de entorno del sistema
$env:ANDROID_HOME = "$env:LOCALAPPDATA\Android\Sdk"
$env:PATH += ";$env:ANDROID_HOME\platform-tools"
$env:PATH += ";$env:ANDROID_HOME\tools"
$env:PATH += ";$env:ANDROID_HOME\tools\bin"
```

Para hacerlo permanente, agregar al perfil de PowerShell o variables de sistema.

### 3. Instalar JDK 17+
- Descargar desde: https://adoptium.net/ (Temurin)
- O usar: `winget install EclipseAdoptium.Temurin.17.JDK`

## Instalación

### Paso 1: Instalar dependencias

```bash
cd frontend
npm install
```

### Paso 2: Compilar la aplicación web

```bash
npm run build
```

### Paso 3: Inicializar Android

```bash
npm run android:init
```

Esto creará la carpeta `android/` con el proyecto nativo.

### Paso 4: Sincronizar

```bash
npm run android:sync
```

## Desarrollo

### Ejecutar en Emulador o Dispositivo

```bash
# Abrir en Android Studio
npm run android:open

# O ejecutar directamente
npm run android:run
```

### Live Reload (Desarrollo)

Para desarrollo con recarga en vivo:

1. Editar `capacitor.config.ts` y descomentar:
```typescript
server: {
  url: 'http://TU_IP_LOCAL:5173',
  cleartext: true,
}
```

2. Ejecutar:
```bash
# Terminal 1: Servidor de desarrollo
npm run dev

# Terminal 2: App Android con live reload
npm run android:dev
```

> **Nota**: Obtén tu IP local con `ipconfig` en Windows

## Configuración del Proyecto Android

### Personalizar Icono y Splash Screen

Los recursos se encuentran en:
```
android/app/src/main/res/
├── drawable/           # Splash screen
├── mipmap-hdpi/        # Iconos 72x72
├── mipmap-mdpi/        # Iconos 48x48
├── mipmap-xhdpi/       # Iconos 96x96
├── mipmap-xxhdpi/      # Iconos 144x144
├── mipmap-xxxhdpi/     # Iconos 192x192
```

Para generar iconos automáticamente, usa:
- https://romannurik.github.io/AndroidAssetStudio/
- O el plugin `cordova-res`: `npx cordova-res android --skip-config --copy`

### Configurar Permisos

En `android/app/src/main/AndroidManifest.xml`, ya vienen configurados:
- Internet
- Estado de red

Para agregar más permisos según los plugins que uses:

```xml
<!-- Notificaciones Push -->
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>

<!-- Cámara -->
<uses-permission android:name="android.permission.CAMERA"/>

<!-- Almacenamiento -->
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE"/>
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE"/>
```

## Configuración de Firebase (Push Notifications)

### 1. Crear proyecto en Firebase
- Ir a https://console.firebase.google.com/
- Crear nuevo proyecto o usar existente
- Agregar app Android con package name: `com.autoservice.itsm`

### 2. Descargar google-services.json
- Colocar en `android/app/google-services.json`

### 3. Configurar Gradle

En `android/build.gradle`:
```gradle
buildscript {
    dependencies {
        classpath 'com.google.gms:google-services:4.4.0'
    }
}
```

En `android/app/build.gradle`:
```gradle
apply plugin: 'com.google.gms.google-services'
```

### 4. Usar en la app

```typescript
import { usePushNotifications } from '@/composables/usePushNotifications';

const { requestPermissions, initPushNotifications }  = usePushNotifications();

// En el montaje de tu app
await requestPermissions();
initPushNotifications();
```

## Build de Producción

### Generar APK

```bash
# Compilar web
npm run build

# Sincronizar con Android
npm run cap:sync

# Abrir Android Studio
npm run android:open
```

En Android Studio:
1. Build → Generate Signed Bundle / APK
2. Seleccionar APK
3. Crear o usar keystore existente
4. Seleccionar release
5. El APK estará en `android/app/release/`

### Generar AAB (Para Google Play)

Mismo proceso pero seleccionar "Android App Bundle" en lugar de APK.

## Scripts Disponibles

| Script | Descripción |
|--------|-------------|
| `npm run android:init` | Inicializar proyecto Android |
| `npm run android:sync` | Compilar web + sincronizar con Android |
| `npm run android:open` | Abrir en Android Studio |
| `npm run android:run` | Compilar y ejecutar en dispositivo/emulador |
| `npm run android:dev` | Desarrollo con live reload |
| `npm run cap:sync` | Solo sincronizar (sin compilar) |

## Plugins Capacitor Incluidos

| Plugin | Uso |
|--------|-----|
| `@capacitor/app` | Manejo de ciclo de vida y back button |
| `@capacitor/haptics` | Vibración |
| `@capacitor/keyboard` | Control de teclado |
| `@capacitor/network` | Estado de conexión |
| `@capacitor/push-notifications` | Notificaciones push |
| `@capacitor/splash-screen` | Pantalla de carga |
| `@capacitor/status-bar` | Personalizar barra de estado |
| `@capacitor/storage` | Almacenamiento persistente |

## Solución de Problemas

### Error: "Android SDK not found"
```bash
# Verificar que ANDROID_HOME esté configurado
echo $env:ANDROID_HOME

# Debería mostrar algo como:
# C:\Users\TuUsuario\AppData\Local\Android\Sdk
```

### Error: "Unable to find a matching variant"
```bash
# Limpiar y reconstruir
cd android
./gradlew clean
cd ..
npm run android:sync
```

### App no conecta al backend
1. Verificar que el backend esté accesible desde el dispositivo
2. Para desarrollo local, usar la IP de tu computadora, no `localhost`
3. Configurar `cleartext: true` en capacitor.config.ts para HTTP

### Depuración
- Usar Chrome en `chrome://inspect` para ver la consola
- En Android Studio, usar Logcat con filtro `Capacitor`

## Estructura de Archivos Android

```
android/
├── app/
│   ├── src/
│   │   ├── main/
│   │   │   ├── AndroidManifest.xml    # Permisos y configuración
│   │   │   ├── java/                   # Código nativo (si es necesario)
│   │   │   └── res/                    # Recursos (iconos, splash)
│   │   └── release/
│   │       └── app-release.apk        # APK de producción
│   ├── build.gradle                   # Configuración de la app
│   └── google-services.json           # Firebase (si usas push)
├── build.gradle                       # Configuración del proyecto
└── gradle.properties                  # Propiedades de Gradle
```

## Próximos Pasos

1. ✅ Configuración base completada
2. ⬜ Personalizar iconos y splash screen
3. ⬜ Configurar Firebase para push notifications
4. ⬜ Probar en dispositivo real
5. ⬜ Firmar y subir a Google Play

---

Para más información, visita la [documentación oficial de Capacitor](https://capacitorjs.com/docs/android).
