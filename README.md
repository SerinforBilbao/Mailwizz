# MailWizz – Personalizaciones

Este documento describe las funciones, extensiones y correcciones personalizadas implementadas sobre **MailWizz Email Marketing** para su integración con MailBys.

> **Importante:** algunos de estos cambios modifican archivos del núcleo de MailWizz y pueden perderse durante una actualización. Después de actualizar MailWizz, se deben revisar y, cuando corresponda, reaplicar las modificaciones descritas en este documento.

---

## 1. Generación automática de API Key

Al registrar un usuario mediante la API de MailWizz, se genera automáticamente una **API Key asociada al nuevo cliente**.

Anteriormente, esta clave debía generarse posteriormente desde el backend de MailWizz.

### Archivo modificado

Desde la raíz del proyecto:

```text
apps/api/controllers/CustomersController.php
```

### Método afectado

```text
actionCreate
```

### Cambio realizado

Después de guardar correctamente la información del cliente, se crea una nueva API Key:

```php
// Crear clave API automáticamente
$apiKey = new CustomerApiKey();
$apiKey->customer_id = (int) $customer->customer_id;
$apiKey->name = 'Client API Key';
$apiKey->description = 'Generated automatically';
$apiKey->save(); // La clave se genera automáticamente en beforeValidate()
```

### Consideraciones de actualización

Este cambio modifica directamente:

```text
apps/api/controllers/CustomersController.php
```

Por lo tanto, después de actualizar MailWizz se debe verificar que la generación automática de la API Key continúe presente dentro de `actionCreate`.

---

## 2. Confirmación de registro

Se modificó el comportamiento posterior a la **confirmación de registro de un cliente**.

Por defecto, MailWizz redirige al usuario nuevamente hacia su propia aplicación. MailBys requiere que, después de la confirmación, el usuario sea enviado a la plataforma MailBys.

### Archivo modificado

```text
apps/customer/controllers/GuestController.php
```

### Método afectado

```text
actionConfirm_registration
```

### Redirección cuando no se encuentra el modelo

#### Antes

```php
$this->redirect(['guest/index']);
```

#### Después

```php
// Custom redirect
$this->redirect('https://mailbys.com');
```

### Redirección después de confirmar el usuario

Las demás redirecciones deben incluir el `customer_uid` del cliente confirmado.

#### Antes

```php
$this->redirect(['guest/index']);

// o

$this->redirect(['account/index']);
```

#### Después

```php
// Custom redirect
$this->redirect(
    'https://mailbys.com/user/confirmation/' . $model->customer_uid
);
```

### Login automático deshabilitado

MailWizz iniciaba automáticamente una sesión después de confirmar el registro.

Este comportamiento fue deshabilitado debido a que la autenticación y continuidad del proceso se realizan desde MailBys.

#### Código original

```php
$identity = new CustomerIdentity($model->email, $model->password);
$identity->setId($model->customer_id);
$identity->setAutoLoginToken($model);

if (!customer()->login($identity, 3600 * 24 * 30)) {
    $this->redirect(
        'https://mailbys.com/user/confirmation/' . $model->customer_uid
    );
}
```

#### Código modificado

```php
// Automatic login after confirmation disabled

/*
$identity = new CustomerIdentity($model->email, $model->password);
$identity->setId($model->customer_id);
$identity->setAutoLoginToken($model);

if (!customer()->login($identity, 3600 * 24 * 30)) {
    $this->redirect(
        'https://mailbys.com/user/confirmation/' . $model->customer_uid
    );
}
*/
```

### Consideraciones de actualización

Después de actualizar MailWizz se debe revisar:

- La redirección de `actionConfirm_registration`.
- La inclusión del `customer_uid` en la URL de confirmación.
- Que el login automático continúe deshabilitado.

---

## 3. Etiquetas personalizadas (Custom Tags)

Se implementaron etiquetas personalizadas para utilizarlas dentro de las plantillas de email.

Ejemplo:

```text
[MAILBYS_UNSUBSCRIBE_URL]
```

El objetivo es generar enlaces directos hacia MailBys y evitar que determinados enlaces sean envueltos por el mecanismo de tracking y redirección de MailWizz.

### Archivo personalizado

```text
apps/init-custom.php
```

### Hook utilizado

```text
campaigns_get_common_tags_search_replace
```

Este hook permite agregar etiquetas personalizadas al conjunto global de búsqueda y reemplazo utilizado durante la generación de campañas.

### Deshabilitar tracking

En los enlaces que no deben ser procesados por el sistema de tracking se agrega:

```text
&disable-tracking=true
```

MailWizz detecta este parámetro durante el procesamiento de los enlaces y evita envolverlos con URLs de seguimiento del tipo:

```text
/campaigns/.../track-url/...
```

### Carga de `init-custom.php`

El archivo personalizado es cargado desde:

```text
apps/init.php
```

mediante:

```php
if (is_file($customInitFile = dirname(__FILE__) . '/init-custom.php')) {
    require $customInitFile;
}
unset($customInitFile);
```

### Consideraciones de actualización

Aunque la lógica personalizada se encuentra aislada en:

```text
apps/init-custom.php
```

su carga depende de una modificación realizada en:

```text
apps/init.php
```

Por lo tanto, **no se debe asumir que esta personalización sobrevivirá automáticamente a una actualización de MailWizz**.

Después de cada actualización:

1. Verificar que `apps/init-custom.php` continúe existiendo.
2. Verificar que `apps/init.php` continúe cargando `init-custom.php`.
3. Si la llamada fue eliminada, agregar nuevamente el bloque anterior.
4. Realizar una prueba de reemplazo de las etiquetas personalizadas.

### Permisos

El archivo debe ser accesible por el usuario utilizado por el servidor web.

Por ejemplo:

```bash
chown www-data:www-data apps/init-custom.php
```

---

## 4. Controladores personalizados de API

Se agregaron controladores personalizados para extender las funcionalidades disponibles mediante la API de MailWizz.

### Ubicación

```text
apps/api/controllers/
```

### Archivos personalizados

```text
Custom_customergroupController.php
Custom_apiController.php
Custom_customersController.php
```

Estos archivos deben conservarse o restaurarse después de una actualización en caso de que sean eliminados.

---

## 5. Rutas personalizadas de API

Las rutas necesarias para acceder a los controladores personalizados se encuentran en:

```text
apps/api/config/main.php
```

Dentro de:

```php
'urlManager' => [
    'rules' => [
        // ...
    ],
]
```

se deben registrar las siguientes reglas:

```php
// Custom rules
['custom_api/getapikey', 'pattern' => 'custom-api/<customer_uid:([a-z0-9]+)>', 'verb' => 'GET'],
['custom_customers/getcustomerinfo', 'pattern' => 'custom-customer/<customer_uid:([a-z0-9]+)>', 'verb' => 'GET'],
['custom_customers/updatecustomergroup', 'pattern' => 'custom-upcustomergroup/<customer_uid:([a-z0-9]+)>', 'verb' => 'PUT'],
['custom_customergroup/getall', 'pattern' => 'custom-customerallgroups', 'verb' => 'GET'],
['custom_customers/getsendings', 'pattern' => 'custom-customersendings/<customer_uid:([a-z0-9]+)>', 'verb' => 'GET'],
```

### Endpoints personalizados

| Método | Endpoint | Funcionalidad |
|---|---|---|
| `GET` | `/custom-customerallgroups` | Obtener todos los grupos existentes en MailWizz |
| `GET` | `/custom-api/<customer_uid>` | Obtener la API Key de un cliente registrado |
| `GET` | `/custom-customer/<customer_uid>` | Obtener información básica de un cliente |
| `PUT` | `/custom-upcustomergroup/<customer_uid>` | Actualizar el grupo de un cliente |
| `GET` | `/custom-customersendings/<customer_uid>` | Obtener los envíos realizados por el cliente durante el mes actual |

### Actualizar grupo de cliente

Endpoint:

```text
PUT /custom-upcustomergroup/<customer_uid>
```

El `customer_uid` se envía como parte de la URL.

El cuerpo de la petición debe enviarse como JSON:

```json
{
  "group_id": 3
}
```

Con la cabecera:

```text
Content-Type: application/json
```

El endpoint valida que:

- `group_id` esté presente.
- `group_id` sea numérico.
- El grupo exista en MailWizz.
- El `customer_uid` corresponda a un cliente existente.

### Consideraciones de actualización

Después de actualizar MailWizz se debe verificar:

- Que los controladores personalizados continúen presentes.
- Que las reglas personalizadas continúen registradas en `apps/api/config/main.php`.
- Que los endpoints respondan correctamente.
- Que los mecanismos de autenticación/autorización de la API continúen funcionando.

---

## 6. Checklist posterior a una actualización de MailWizz

Después de actualizar MailWizz, verificar:

- [ ] Generación automática de API Key durante el registro.
- [ ] Redirección personalizada después de confirmar una cuenta.
- [ ] Login automático deshabilitado después de la confirmación.
- [ ] Existencia de `apps/init-custom.php`.
- [ ] Carga de `init-custom.php` desde `apps/init.php`.
- [ ] Funcionamiento de `[MAILBYS_UNSUBSCRIBE_URL]` y demás etiquetas personalizadas.
- [ ] Existencia de los controladores personalizados.
- [ ] Existencia de las rutas personalizadas en `apps/api/config/main.php`.
- [ ] Funcionamiento de los endpoints personalizados.
- [ ] Permisos de los archivos personalizados.

---

## 7. Registro de cambios

Cada nueva personalización debe documentarse indicando como mínimo:

```text
Versión MailWizz: 2.x
Fecha: YYYY-MM-DD
Autor: <autor>
Descripción: <descripción del cambio>
Archivos afectados:
- <archivo 1>
- <archivo 2>
```

Ejemplo:

```text
Versión MailWizz: 2.x
Fecha: 2026-01-16
Autor: Cosme Fulanito
Descripción: Implementación de generación automática de API Key al crear clientes mediante la API.
Archivos afectados:
- apps/api/controllers/CustomersController.php
```

---

## Convención recomendada para cambios personalizados

Para facilitar la identificación de modificaciones propias después de una actualización, se recomienda marcar el código personalizado con:

```php
// MAILBYS CUSTOM
```

Esto permite localizar rápidamente las modificaciones mediante:

```bash
grep -R "MAILBYS CUSTOM" apps/
```
