# MailWizz – Personalizaciones

Funciones y correcciones personalizadas sobre la aplicación de **MailWizz Email Marketing**.  
**Nota:** Estos cambios se perderán al actualizar MailWizz, por lo que deben reaplicarse tras cada actualización.

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

## Información de cambios
Añadir en todos los cambios realizados la siguiente información:
```
Versión MailWizz: 2.x

Fecha: 2026-01-16

Autor: Cosme Fulanito
```

