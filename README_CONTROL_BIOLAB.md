# Control BIOLAB

Sistema web iniciado a partir del archivo `Seño_Yohana.xlsm`.

## Que se detecto del Excel

- Menu principal con 21 plantillas de laboratorio.
- Plantillas de resultado con datos del paciente, fecha, medico que refiere, tabla de analisis, resultado, unidades y valores normales.
- Acciones originales: menu, PDF, nueva plantilla, editar, imprimir y bloqueo.
- El Excel contiene macros, pero la logica principal es reemplazable por formularios web, base de datos e impresion/PDF.

## Primer modulo implementado

- Tablero principal con categorias de examen.
- Formulario de nuevo resultado.
- Vista previa imprimible.
- Estructura inicial de migraciones para pacientes, categorias, pruebas, ordenes y resultados.
- Dockerfile y `render.yaml` base para desplegar en Render con PostgreSQL.

## Ejecutar local

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Abrir:

```text
http://127.0.0.1:8001
```

## Nota local de base de datos

El PHP instalado en esta maquina no tiene activo `pdo_sqlite`. Por eso el prototipo usa sesiones en archivo y por ahora no depende de base de datos para mostrar el flujo. Para guardar resultados localmente se puede:

- habilitar `pdo_sqlite`, o
- conectar MySQL/PostgreSQL desde `.env`.

En Render se recomienda PostgreSQL.

## Siguiente punto de desarrollo

Convertir las plantillas de `config/lab.php` en datos reales con seeders, guardar pacientes/resultados en la base de datos y agregar historial/busqueda de resultados.
