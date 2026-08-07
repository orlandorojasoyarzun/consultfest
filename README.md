# Consultfest

**Film Festival Distribution CRM** - Una herramienta para cineastas que buscan festivales de cine y quieren recibir notificaciones antes de los deadlines.

## Qué es

Consultfest permite:
- **Buscar festivales** por rango de fechas, categoría, género y país
- **Registrarse** con nombre, email y teléfono para recibir notificaciones
- **Suscribirse** a festivales específicos
- **Recibir emails recordatorio** cuando un festival está por cerrar o abrir

## Arquitectura

### Stack Tecnológico

| Componente | Decisión | Justificación |
|------------|----------|---------------|
| **Framework** | Laravel 13 | Robustez, ecosistema maduro, Eloquent ORM |
| **Frontend** | Livewire 4 | Componentes reactivos sin boilerplate JS |
| **CSS** | Tailwind CSS v4 | Diseño rápido con variables CSS personalizadas |
| **Base de datos dev** | SQLite | Zero-config, ideal para desarrollo local |
| **Email** | Resend | API simple, integración directa con Laravel |
| **Búsqueda API** | FestivalAPI | API pública de festivales de cine |

### Decisiones de Diseño

**1. Sin autenticación de usuario**
- Es una herramienta pública de búsqueda
- El usuario se registra solo para recibir notificaciones
- La sesión se maneja via `session()` de Laravel (cookie)
- No hay passwords, no hay login complejo

**2. Modelo de datos simplificado**
```
Subscriber (id, name, email, phone, notifications_enabled)
    └── Subscription (subscriber_id, festival_id, notification_type)
            └── Festival (id, api_id, name, category, country, deadline, ...)
```

**3. Festivales desde API externa**
- Los festivales se sincronizan desde FestivalAPI
- Se guardan en la base de datos local
- El `FestivalApiService` maneja la comunicación con la API
- `SyncFestivals` command sincroniza periódicamente

**4. Notificaciones basadas en suscripciones**
- El usuario suscribe a festivales específicos
- Un command (`festivals:check-deadlines`) corre diariamente
- Envía emails 7 y 30 días antes del deadline/apertura

### Estructura del Proyecto

```
app/
├── Console/Commands/
│   ├── SyncFestivals.php          # Sincroniza festivales desde API
│   └── CheckFestivalDeadlines.php # Verifica y envía notificaciones
├── Http/Controllers/
│   └── FestivalController.php      # Rutas API y web
├── Livewire/
│   ├── FestivalCalendar.php       # Filtros de búsqueda
│   ├── FestivalResults.php        # Lista de resultados
│   └── SubscriberForm.php         # Formulario de registro
├── Models/
│   ├── Festival.php               # Festival de cine
│   ├── Subscriber.php            # Usuario registrado
│   └── Subscription.php           # Relación subscriber-festival
├── Notifications/
│   ├── FestivalDeadlineNotification.php
│   └── FestivalOpeningNotification.php
└── Services/
    ├── FestivalApiService.php     # Cliente de API externa
    └── NotificationService.php     # Lógica de notificaciones

database/
├── migrations/
│   ├── ...create_festivals_table.php
│   ├── ...create_subscribers_table.php
│   └── ...create_subscriptions_table.php
├── factories/
│   ├── FestivalFactory.php
│   ├── SubscriberFactory.php
│   └── SubscriptionFactory.php
└── seeders/
    └── FestivalSeeder.php          # 30 festivales de prueba

resources/views/
├── welcome.blade.php             # Página principal (2 columnas)
├── festivals/
│   ├── index.blade.php           # Lista paginada
│   └── show.blade.php            # Detalle de festival
└── livewire/
    ├── festival-calendar.blade.php
    ├── festival-results.blade.php
    └── subscriber-form.blade.php
```

## Setup Local

```bash
# Instalar dependencias
composer install
npm install

# Copiar entorno
cp .env.example .env

# Generar key
php artisan key:generate

# Migrar y seedear
php artisan migrate:fresh --seed

# Construir assets
npm run build

# Iniciar servidor
php artisan serve
```

## Comandos Disponibles

```bash
# Sincronizar festivales desde API
php artisan festivals:sync

# Verificar deadlines y enviar notificaciones
php artisan festivals:check-deadlines

# Verificar un día específico
php artisan festivals:check-deadlines --days=7
```

## Scheduler (notificaciones automáticas)

Los comandos `festivals:sync` (06:00) y `festivals:check-deadlines` (08:00) corren
vía Laravel scheduler. Para que se ejecuten automáticamente hay que registrar un
LaunchAgent de macOS:

```bash
cp scripts/com.consultfest.scheduler.plist ~/Library/LaunchAgents/
launchctl load ~/Library/LaunchAgents/com.consultfest.scheduler.plist
```

Verificar que está corriendo:

```bash
launchctl list | grep consultfest
tail -f storage/logs/scheduler.log
```

Para detenerlo:

```bash
launchctl unload ~/Library/LaunchAgents/com.consultfest.scheduler.plist
```

En Linux usar `crontab -e` con la entrada:

```
* * * * * cd /path/to/consultfest && php artisan schedule:run >> /dev/null 2>&1
```

## API Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/` | Página principal |
| GET | `/festivals` | Lista paginada de festivales |
| GET | `/festivals/{id}` | Detalle de festival |
| GET | `/festivals/search` | Búsqueda con filtros (JSON) |
| POST | `/subscribe` | Suscribirse a un festival |
| DELETE | `/unsubscribe/{api_id}` | Desuscribirse |

## Tests

```bash
php artisan test
# 58 tests, 74 assertions
```

## Decisiones Pendientes

- [ ] Integración real con FestivalAPI (API key no disponible aún)
- [ ] Configurar scheduler de Laravel para notifications automáticas
- [ ] Dashboard de estadísticas para el usuario registrado
- [ ] Historial de notificaciones enviadas

## Conceptos Clave

**Subscriber**: Cineasta registrado que quiere recibir notificaciones
**Subscription**: Relación entre un subscriber y un festival específico
**NotificationType**: `opening` | `deadline` | `both`
**Festival Score**: Puntuación del festival (de la API externa)

---

*Última actualización: Julio 2026*
