# MailWizz – Personalizaciones

Funciones y correcciones personalizadas sobre la aplicación de **MailWizz Email Marketing**.  
**Nota:** Estos cambios se perderán al actualizar MailWizz, por lo que deben reaplicarse tras cada actualización.

---
## Generar Api Key

Al momento de registrar un usuario por medio de la API se **genera nueva api key para ese usuario**.  
Por defecto, este proceso se realizaba desde el Backend de Mailwizz, ahora es automático.

### Archivo modificado

Desde la raíz del proyecto, ubicar el archivo:

apps/api/controllers/CustomersController.php

### Método afectado

Buscar la función:

actionCreate

### Cambio generado
Justo después de guardar la información del cliente se debe generar la api key y guardarla, para ello se inserta el siguiente código:
```
// Crear clave API automáticamente
    $apiKey = new CustomerApiKey();
    $apiKey->customer_id = (int)$customer->customer_id;
    $apiKey->name = 'Client API Key';
    $apiKey->description = 'Generated automatically';
    $apiKey->save(); // Aquí se genera automáticamente la clave en beforeValidate()
```
---
---

## Confirmación de registro

Se realiza un cambio en la **redirección al confirmar el registro**.  
Por defecto, MailWizz redirige al backend, pero ahora el usuario será enviado a: https://mailbys.com

### Archivo modificado

Desde la raíz del proyecto, ubicar el archivo:

apps/customer/controllers/GuestController.php

### Método afectado

Buscar la función:

actionConfirm_registration


### Cambio en la redirección
**Cuando no se obtienen resultados al consultar el modelo.**

#### Antes
```
$this->redirect(['guest/index']);
```
#### Después
```
// Custom redirect
$this->redirect('https://mailbys.com');
```

**Para las demás redirecciones se debe incluir el customer_uid devuelto por el modelo.**

#### Antes
```
$this->redirect(['guest/index']);
// o
$this->redirect(['account/index']);
```

#### Después
```
// Custom redirect
$this->redirect('https://mailbys.com/user/confirmation/' . $model->customer_uid);
```

### Login automático deshabilitado
**Tras confirmar el usuario, se iniciaba una sesión automáticamente; ahora basta con comentar las líneas correspondientes.**

#### Antes
```
$identity = new CustomerIdentity($model->email, $model->password);
$identity->setId($model->customer_id);
$identity->setAutoLoginToken($model);

if (!customer()->login($identity, 3600 * 24 * 30)) {
    //$this->redirect(['guest/index']);
    //custom redirect
    $this->redirect('https://mailbys.com/user/confirmation/'.$model->customer_uid);
}
```
#### Después
```
//automatic login after confirmation will be disabled
/* $identity = new CustomerIdentity($model->email, $model->password);
$identity->setId($model->customer_id);
$identity->setAutoLoginToken($model);

if (!customer()->login($identity, 3600 * 24 * 30)) {
    //$this->redirect(['guest/index']);
    //custom redirect
    $this->redirect('https://mailbys.com/user/confirmation/'.$model->customer_uid);
} */
```
---
---

## Etiquetas Personalizadas (Custom Tags)
Se han implementado etiquetas personalizadas para usar en las plantillas de email (ej: [MAILBYS_UNSUBSCRIBE_URL]). El objetivo es generar enlaces directos a nuestra plataforma, evitando el sistema de tracking y redirección interna de MailWizz (que suele generar URLs largas tipo /campaigns/.../track-url/).

#### Archivo implementado
La lógica se encuentra en un archivo personalizado que MailWizz respeta durante las actualizaciones (aunque requiere verificación):
apps/init-custom.php

#### Detalles Técnicos
1. **Hook Utilizado:** (campaigns_get_common_tags_search_replace)
   Este hook permite inyectar etiquetas en el array de búsqueda/reemplazo global de las campañas.
2. **Evitar Tracking:** Se añade el parámetro &disable-tracking=true a las URLs generadas.
   MailWizz detecta este parámetro internamente (en CampaignHelper.php) y omite envolver el enlace con su sistema de seguimiento.

#### Mantenimiento y Actualizaciones
El archivo init-custom.php se carga automáticamente gracias a una instrucción en el archivo núcleo apps/init.php.
* **Importante:** Al actualizar MailWizz, el archivo apps/init.php puede ser sobrescrito, eliminando la llamada a nuestro archivo custom.
* **Solución:** Tras actualizar, verificar que el archivo apps/init.php contenga el siguiente bloque al final. Si no existe, agregarlo manualmente:

```
    if (is_file($customInitFile = dirname(__FILE__) . '/init-custom.php')) {
        require $customInitFile;
    }
    unset($customInitFile);
```

#### Permisos
Asegurarse de que apps/init-custom.php tenga como propietario al usuario del servidor web (ej. www-data):

```
chown www-data:www-data apps/init-custom.php
```
---
---

## Información de cambios
Añadir en todos los cambios realizados la siguiente información:
```
Versión MailWizz: 2.x

Fecha: 2026-01-16

Autor: Cosme Fulanito
```

