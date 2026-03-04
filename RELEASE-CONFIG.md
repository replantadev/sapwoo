# SAP Woo Suite - Release Configuration

> **⚠️ ARCHIVO CENTRAL DE VERSIONES**
> Cuando actualices una versión, TODOS estos archivos deben sincronizarse.
> Copia esta sección completa al asistente de IA para que actualice todo automáticamente.

---

## 📦 Versiones Actuales

| Plugin | Versión | Estado | Repositorio |
|--------|---------|--------|-------------|
| **SAP Woo Suite PRO** | `2.9.0` | Estable | [replantadev/sap-woo-suite](https://github.com/replantadev/sap-woo-suite) (privado) |
| **SAP Woo Suite Lite** | `1.0.0` | Estable | [replantadev/sap-woo-suite-lite](https://github.com/replantadev/sap-woo-suite-lite) (público) |

---

## 🎨 Assets

| Asset | URL |
|-------|-----|
| **Plugin Icon** | `https://replanta.net/wp-content/uploads/2026/03/sapwoosuite-ico.png` |
| **Replanta Logo** | `https://replanta.net/wp-content/uploads/2025/12/icono.png` |
| **SAP Logo** | `https://replanta.net/wp-content/uploads/2026/03/sap.svg` |
| **WooCommerce Logo** | `https://replanta.net/wp-content/uploads/2026/03/woocommerce.svg` |

---

## 🔗 URLs Oficiales

| Recurso | URL |
|---------|-----|
| **Documentación** | `https://replantadev.github.io/sapwoo/` |
| **Landing Conector** | `https://replanta.net/conector-sap-woocommerce/` |
| **Landing Plugins** | `https://replanta.net/plugins/` |
| **GitHub Org** | `https://github.com/replantadev` |
| **Contacto** | `https://replanta.net/contacto` |

---

## 📝 Archivos a Actualizar (Checklist)

Cuando cambies la versión de PRO o Lite, actualiza **TODOS** estos archivos:

### Plugin PRO (sap-woo-suite/)
- [ ] `sap-woo-suite.php` → línea `Version: X.X.X`
- [ ] `README.md` → badge de versión
- [ ] `readme.txt` → `Stable tag: X.X.X`
- [ ] `CHANGELOG.md` → añadir nueva entrada

### Plugin Lite (sap-woo-suite-lite/)
- [ ] `sap-woo-suite-lite.php` → línea `Version: X.X.X`
- [ ] `README.md` → badge de versión
- [ ] `readme.txt` → `Stable tag: X.X.X`

### Documentación (sap-woo/docs/)
- [ ] `README.md` → badges de versiones
- [ ] `changelog.md` → añadir nueva entrada
- [ ] `_sidebar.md` → actualizar enlaces de versión

### Landings Web
- [ ] `landing.html` → versión en hero, panel, badges
- [ ] `plugins-landing.html` → versión en badges y featured

---

## 🚀 Proceso de Release

### 1. Actualizar versión en plugin principal
```bash
# PRO
cd wp-content/plugins/sap-woo-suite
# Editar sap-woo-suite.php, README.md, readme.txt

# Lite
cd wp-content/plugins/sap-woo-suite-lite
# Editar sap-woo-suite-lite.php, README.md, readme.txt
```

### 2. Actualizar este archivo RELEASE-CONFIG.md
⬆️ Cambiar la tabla de versiones al inicio

### 3. Actualizar documentación
```bash
cd wp-content/plugins/sap-woo/docs
# Editar README.md, changelog.md, _sidebar.md
```

### 4. Actualizar landings
```bash
# landing.html y plugins-landing.html en /app/public/
# Buscar y reemplazar versión anterior por nueva
```

### 5. Commit y push
```bash
# Repos a actualizar:
git push origin main  # sap-woo-suite
git push origin main  # sap-woo-suite-lite  
git push origin main  # sap-woo (docs - GitHub Pages)
```

### 6. Subir landing a producción
Las landings están en el servidor de replanta.net

---

## 🤖 Prompt para Actualización Automática

Copia y pega esto al asistente de IA cuando necesites actualizar versiones:

```
Actualiza la versión de SAP Woo Suite PRO a X.X.X
(y/o Lite a Y.Y.Y)

Archivos a modificar:
1. sap-woo-suite/sap-woo-suite.php (Version: X.X.X)
2. sap-woo-suite/README.md (badge versión)
3. sap-woo-suite/readme.txt (Stable tag)
4. sap-woo-suite/CHANGELOG.md (nueva entrada)
5. sap-woo-suite-lite/sap-woo-suite-lite.php (si Lite cambió)
6. sap-woo-suite-lite/README.md (si Lite cambió)
7. sap-woo/docs/README.md (badges)
8. sap-woo/docs/changelog.md (nueva entrada)
9. sap-woo/docs/_sidebar.md (versiones)
10. landing.html (todas las menciones de versión)
11. plugins-landing.html (todas las menciones de versión)
12. RELEASE-CONFIG.md (tabla de versiones)

Luego haz push a:
- replantadev/sap-woo-suite
- replantadev/sap-woo-suite-lite
- replantadev/sap-woo (docs)
```

---

## 📊 Brand Colors

```css
--mint-green: #93f1c9;
--dark-forest: #1e2f23;
--teal-main: #41999f;
--sun-yellow: #f7d450;
--light-mint: #92f1cb;
```

---

*Última actualización: 2026-03-04*
