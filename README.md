# 🏢 Monsters Inc. ERP & Tienda Online — Solución Corporativa e Integración de Ventas

Este proyecto es una plataforma empresarial integrada que consta de un sistema ERP y una tienda de e-commerce, ambos conectados a una única base de datos MySQL para garantizar la consistencia y la centralización de la información.

1.  **🛒 Tienda de Monsters Inc (`tienda-de-Monsters-Inc`)**: Canal de e-commerce donde los clientes finales consultan stock en tiempo real y realizan compras.
2.  **💼 Monsters Inc ERP (`ERP-Monsters-Inc`)**: Consola interna de administración para la gestión de inventarios, procesamiento de ventas, facturación (CFDI), cortes de caja y un robusto control de accesos basado en roles (RBAC).

---

## ️ Stack Tecnológico

*   **Backend**: PHP 8.0+
*   **Servidor Web**: Apache
*   **Base de Datos**: MySQL
*   **Conexión a BD**: PDO (PHP Data Objects) para un acceso estandarizado y seguro.
*   **Frontend**: HTML5, CSS3, JavaScript (Vanilla)

---

## 📂 Estructura del Proyecto

El repositorio está organizado para separar claramente las responsabilidades del frontend y el backend:

-   `/backend`: Contiene toda la lógica de la API REST.
    -   `/config`: Conexión a la base de datos y configuración de CORS.
    -   `/controllers`: Lógica de negocio y manejo de peticiones.
    -   `/helpers`: Funciones de utilidad (autenticación, auditoría, respuestas JSON).
    -   `/middleware`: Scripts para validación de sesiones y permisos.
    -   `/models`: Clases que interactúan directamente con la base de datos.
    -   `/routes`: Puntos de entrada de la API que mapean las URLs a los controladores.
-   `/ERP-Monsters-Inc`: Contiene la interfaz de usuario del sistema ERP.
    -   `/assets`: Archivos CSS, JS e imágenes.
    -   `/pages`: Vistas HTML de cada módulo del ERP.
-   `/tienda-de-Monsters-Inc`: Contiene la interfaz de usuario de la tienda online.
-   `MonsterInc.sql`: Archivo con el esquema completo de la base de datos para una fácil importación.

---

## 📈 Resolución de la Problemática del Negocio

| Situación a Resolver (Caso de Estudio) | Implementación Técnica y de Base de Datos | Componente del ERP / Tienda |
| :--- | :--- | :--- |
| **Múltiples canales de venta inconexos** | Unificación en la tabla `VENTA` con el atributo `canal_venta` (`ENUM('Linea', 'Fisica', 'Corporativo')`). | Dashboard consolidado en tiempo real. |
| **Precios varían por canal** | Tabla relacional `PRECIO_CANAL` para fijar montos específicos por SKU, canal y rango de fechas. | Tienda (Canal Linea) y ERP (Punto Físico / Corporativo). |
| **Clientes duplicados o incompletos** | Normalización (3FN) separando `CLIENTE`, `DIRECCION_CLIENTE` y `CUENTA_CLIENTE`. | Módulo de Clientes del ERP. |
| **Productos cambian de categoría** | Tabla `CATEGORIA_PRODUCTO` con vigencia temporal (`fecha_inicio`, `fecha_fin`) para rastreo histórico. | Base de datos e Inventarios. |
| **Información no uniforme por canal** | Las tablas `ENVIO` y `ESTADO_ENVIO` solo se asocian a ventas no presenciales. | Procesamiento de ventas. |
| **Análisis por períodos de tiempo** | Consultas dinámicas sobre `VENTA` filtrando por `fecha_venta`. | Corte de caja (Semanal, Mensual, Rango Libre). |
| **Visión consolidada y reportes manuales** | Consultas agregadas (`SUM`, `COUNT`) mostradas en gráficos interactivos. | Dashboard principal con descarga en CSV. |

---

## 💎 Características Premium del ERP

*   **⚡ Notificaciones Toast**: Alertas de validación, éxito y error elegantes que reemplazan los `alert()` nativos.
*   **📉 Dashboard Analítico**: Gráficas interactivas que consolidan ventas netas por canal y origen de clientes.
*   **🧾 Autorelleno de Importe de CFDI**: El sistema sugiere el monto de la factura basándose en el historial de consumo del cliente seleccionado.
*   **🔐 Modificación de Permisos en Caliente**: Los administradores pueden cambiar privilegios de un rol y ver los cambios reflejados en el menú de la UI al instante.
*   **📦 Scrollbar Personalizado**: Un diseño de scroll minimalista en la barra lateral para una mejor experiencia de usuario.

---

## 🔑 Credenciales del ERP (Acceso por Roles)

El sistema se entrega con 5 cuentas de prueba configuradas con distintos niveles de acceso:

| Rol de Empleado | Correo de Acceso | Contraseña | Permisos Clave |
| :--- | :--- | :--- | :--- |
| **Administrador General** | `admin@monsters.com` | `Admin123*` | Acceso Total a todos los módulos |
| **Gerente Sucursal** | `gerente@monsters.com` | `Gerente123*` | Tablero, Ventas, Clientes, Inventarios, Corte |
| **Vendedor** | `vendedor@monsters.com` | `Vendedor123*` | Tablero, Ventas, Clientes |
| **Personal de Almacén** | `almacen@monsters.com` | `Almacen123*` | Tablero, Inventarios |
| **Contador** | `contador@monsters.com` | `Contador123*` | Tablero, Facturación, Corte |

---

## 🚀 Instalación y Configuración Local

### Requisitos Previos
*   **XAMPP** (o similar) con **PHP 8.0+** y **MySQL**.
*   Un gestor de bases de datos como phpMyAdmin o DBeaver.

### Pasos de Ejecución
1.  **Clona o descarga** este repositorio en la carpeta raíz de tu servidor local (ej. `C:/xampp/htdocs/MonsterInc/`).
2.  **Inicia los servicios** de Apache y MySQL desde el panel de control de XAMPP.
3.  **Crea la base de datos**:
    *   Abre phpMyAdmin (o tu gestor de BD preferido).
    *   Crea una nueva base de datos llamada `u930267312_MonsterInc` (con cotejamiento `utf8mb4_unicode_ci`).
    *   Selecciona la base de datos recién creada y ve a la pestaña **Importar**.
    *   Sube y ejecuta el archivo `MonsterInc.sql` que se encuentra en la raíz del proyecto. Esto creará todas las tablas necesarias.
4.  **Configura la conexión**:
    *   Abre el archivo `backend/config/database.php` (si no existe, créalo a partir del de ejemplo).
    *   Asegúrate de que las credenciales (`$host`, `$dbname`, `$username`, `$password`) coincidan con tu configuración de MySQL.
5.  **Poblar datos de prueba (Opcional)**:
    *   La primera vez que accedas al ERP o a la tienda, los scripts de backend detectarán si las tablas están vacías y las poblarán automáticamente con datos de prueba (empleados, productos, etc.).
6.  **Accede a las aplicaciones**:
    *   **Tienda Online**: `http://localhost/MonsterInc/tienda-de-Monsters-Inc/`
    *   **ERP Corporativo**: `http://localhost/MonsterInc/ERP-Monsters-Inc/`
    *(Si usas un puerto diferente al 80, como 8080, ajústalo en la URL: `http://localhost:8080/...`)*

---

## 🧪 Guía de Pruebas Integradas

Para validar la correcta sincronización entre los sistemas, sigue este flujo:

### Prueba A: Compra en Tienda y Sincronización con ERP
1.  Entra a la **Tienda Online** (`tienda-de-Monsters-Inc`).
2.  Agrega un producto al carrito y finaliza la compra.
3.  Inicia sesión en el **ERP** como Administrador (`admin@monsters.com`).
4.  Haz clic en **Recargar datos de BD** en el menú lateral.
5.  **Verifica**:
    *   El total de ventas en el dashboard ha aumentado.
    *   El stock del producto comprado ha disminuido en el módulo de **Inventarios**.
    *   Aparece un nuevo registro en el módulo de **Ventas** con el canal `Linea`.

### Prueba B: Emisión de CFDI (Facturación)
1.  En el ERP, dirígete al menú **Facturación**.
2.  Selecciona un cliente y un canal. El campo **Importe** se autorellenará.
3.  Presiona **Emitir CFDI**. Una notificación confirmará la acción y el registro aparecerá en la tabla.

### Prueba C: Restricción de Accesos (Seguridad RBAC)
1.  Cierra sesión en el ERP presionando **Cerrar sesión** en la barra lateral.
2.  Inicia sesión como Personal de Almacén (`almacen@monsters.com`).
3.  **Verifica**: El menú lateral solo muestra las opciones permitidas para ese rol (Tablero, Inventarios). Cualquier intento de acceder a una URL no autorizada será bloqueado.

---

*Este proyecto fue desarrollado como parte de la materia Bases de Datos Aplicadas en el Tecnológico Nacional de México (TecNM) Campus Pachuca. Ver `CONTRIBUTING.md` para más detalles.*
