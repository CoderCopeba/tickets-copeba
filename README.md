# Copeba Tickets

Sistema de reporte y soporte técnico de la Cooperativa Copeba, R.L.

> **Equipo y agentes de IA:** las instrucciones completas (puesta en marcha, reglas,
> problemas frecuentes y estructura) están en [AGENTS.md](AGENTS.md). Léelo antes de cambiar nada.

## Requisitos

- PHP 8.3 o superior (extensiones `pdo_mysql`, `mbstring`, `gd`, `openssl`) — Laragon
- [Composer](https://getcomposer.org/) (incluido en Laragon)
- Internet: la base de datos es compartida en la nube (**TiDB Cloud**, compatible con MySQL 8)

## Instalación en una computadora nueva

1. Clonar el repositorio en `C:\laragon\www\tickets-copeba` y ejecutar:
   ```
   composer install
   ```
2. Copiar `.env.example` a `.env` y completar los datos de la base de datos.
   Los entrega Informática en persona; **nunca** se envían por chat, correo ni Git.
3. Comprobar la instalación:
   ```
   php instalar/diagnostico.php
   ```
   Debe terminar en **"Todo en orden"**. Si muestra `[FALLA]`, ver "Problemas frecuentes" en [AGENTS.md](AGENTS.md).
4. Abrir la dirección que indica el diagnóstico (normalmente `http://localhost/tickets-copeba/`).

No hay que crear la base ni importar nada: todo el equipo usa la misma base en la nube.
Para trabajar sin internet con una base local, ver el anexo de [AGENTS.md](AGENTS.md).

## Si ya tenías el proyecto

```
git switch main
git pull
composer install
php instalar/diagnostico.php
```

Compara tu `.env` con `.env.example` (agrega las variables nuevas, borra las que ya no existan,
como `CHAT_API_*`) y recarga el navegador con **Ctrl+F5**. Detalle en [AGENTS.md](AGENTS.md).

## Forma de trabajo

- Nadie trabaja en `main`: cada tarea en su rama (`nombre/tarea`) y llega a `main` por Pull Request revisado.
- Los cambios de estructura de la base van en `instalar/migraciones/` y los aplica un administrador una sola vez.
- `.env`, contraseñas y `vendor/` nunca se suben a Git.

## Librerías principales

| Librería | Versión |
|---|---|
| PHPMailer (Composer) | 7.2 |
| jQuery (+ Migrate) | 3.7.1 (3.5.2) |
| Summernote (lite) | 0.9.1 |
| Select2 | 4.0.13 |
| Bootstrap (plantilla StartUI) | 4.0.0-alpha.4 |

Los listados usan el componente propio `Copeba.listado` (`public/js/copeba-listado.js`).
