# Traslado de artefactos al servidor CONAF (172.31.2.41)

Procedimiento para mover al servidor los dos artefactos del Moodle de `cursos.conaf.cl`:
el volcado de la base (295 MB) y el `moodledata` (10,9 GB). Es el paso **F0**: sin esto no
se puede levantar nada.

Todo lo que sigue se ejecuta **desde Git Bash** en el equipo Windows donde están los datos.

---

## 1. Estructura de carpetas en el servidor

```
/opt/apps/coipo_moodle/            ← repositorio desplegado: Dockerfile, compose, .env
                                     Lo sincroniza el deploy automático.
                                     NUNCA poner datos aquí.

/opt/moodledata/coipo_moodle/      ← moodledata, 10,9 GB. Dueño UID 33 (www-data)
├── filedir/                         10,84 GB · 256 subcarpetas (00–ff) · 15.994 archivos
└── lang/                            9,8 MB · paquetes de idioma

/opt/migracion/coipo_moodle/       ← TEMPORAL: el dump. Se borra al terminar la migración
└── bitnami_moodle.sql               295 MB
```

### Por qué `moodledata` va fuera de `/opt/apps/`

1. **Seguridad ante el deploy.** El despliegue automático sincroniza `/opt/apps/<repo>/`.
   Si alguna vez corre con `--delete`, se lleva los 10,9 GB. Separarlo hace imposible ese
   accidente.
2. **Ciclos de vida distintos.** El código se redespliega en cada push; los datos deben
   persistir y respaldarse por separado, con otra frecuencia y otra política.
3. **Crecimiento.** Si `moodledata` necesita un disco propio, se cambia el punto de montaje
   sin tocar la aplicación.

---

## 2. Comprobaciones previas (en el servidor)

```bash
ssh usuario@172.31.2.41

df -h /opt          # ≥ 25 GB libres: 10,9 datos + 0,3 dump + ~3 MariaDB temporal + imágenes
docker --version    # Docker instalado y tu usuario puede usarlo
docker compose version
id                  # ¿tienes sudo?
```

Si `/opt` no tiene espacio, **detente aquí** y resuelve el almacenamiento antes de copiar
nada: un traslado de 10,9 GB que se queda sin disco a mitad de camino hay que rehacerlo.

---

## 3. Crear la estructura

```bash
sudo mkdir -p /opt/moodledata/coipo_moodle/{filedir,lang} /opt/migracion/coipo_moodle
sudo chown -R 33:33 /opt/moodledata/coipo_moodle
sudo chmod -R 0770  /opt/moodledata/coipo_moodle
```

**El `33` no es un error.** Dentro del contenedor, Apache y PHP corren como el usuario
`www-data`, que en las imágenes Debian tiene **UID 33**. El kernel compara números, no
nombres: si la carpeta del host pertenece a otro UID, Moodle no podrá escribir y fallará al
subir archivos. Por eso se usa el número directamente.

---

## 4. Copiar el dump de la base (295 MB)

```bash
SRV=usuario@172.31.2.41

scp "/c/Users/luis.monsalve/Documents/GitHub/coipo_moodle/Moodle/Base de datos sql/bitnami_moodle.sql" \
    $SRV:/opt/migracion/coipo_moodle/
```

---

## 5. Copiar `moodledata` (10,9 GB)

> **`rsync` no está instalado en tu Git Bash** (sí `ssh`, `scp` y `tar`), así que
> `rsync -avhP …` no va a funcionar. Comprimir en `.zip` tampoco ayuda: `filedir` son PDFs e
> imágenes ya comprimidos, y PowerShell 5.1 no puede crear un ZIP de más de 2 GB.

### Opción A — empaquetar y enviar con `scp`

```bash
# 1. Empaquetar SOLO lo que sirve (filedir + lang), desde Git Bash.
#    Sin -z: el contenido ya está comprimido (PDFs, imágenes) y gzip sobre 10,9 GB
#    tarda mucho para ganar casi nada.
tar -cvf /c/moodle.tar -C /c/moodledata filedir lang

# 2. Enviar
scp /c/moodle.tar usuario@172.31.2.41:/opt/migracion/coipo_moodle/

# 3. Desempaquetar en el servidor y devolver la propiedad
ssh usuario@172.31.2.41 '
  sudo tar -xvf /opt/migracion/coipo_moodle/moodle.tar -C /opt/moodledata/coipo_moodle/ &&
  sudo chown -R 33:33 /opt/moodledata/coipo_moodle &&
  rm /opt/migracion/coipo_moodle/moodle.tar'
```

**Dos cosas que conviene saber antes de lanzarlo:**

- **Espacio en disco**: el paquete ocupa otros ~10,7 GB en tu equipo, y en el servidor
  conviven el archivo y lo extraído → **~22 GB libres en `/opt` durante el proceso**.
  Compruébalo antes con `df -h`.
- **`scp` no se reanuda.** Si la conexión se corta al 90 % de 10,7 GB, se empieza de cero.
  Si la red hacia el servidor no es estable, usa la Opción B.

#### Si enviaste un `.zip` de todo `moodledata`

Es el caso real de este proyecto: `moodledata.zip`, 10,67 GB, 39.787 entradas. Tiene dos
particularidades que hay que corregir al desempaquetar.

**1. El zip trae una carpeta `moodledata/` en la raíz.** Si lo extraes directamente sobre
`/opt/moodledata/coipo_moodle/`, el contenido queda un nivel más abajo del esperado
(`.../coipo_moodle/moodledata/filedir/`) y Moodle no encontrará nada. La forma limpia
—y que no necesita espacio extra, porque `mv` dentro del mismo disco solo renombra—:

```bash
cd /opt/moodledata
sudo unzip -q /opt/migracion/coipo_moodle/moodledata.zip -d /opt/moodledata/
# queda /opt/moodledata/moodledata/
sudo rmdir /opt/moodledata/coipo_moodle 2>/dev/null   # solo si está vacía
sudo mv /opt/moodledata/moodledata /opt/moodledata/coipo_moodle
```

Si `unzip` no está instalado: `sudo apt install unzip`, o
`python3 -m zipfile -e /opt/migracion/coipo_moodle/moodledata.zip /opt/moodledata/`.

**2. El zip incluye las carpetas de estado que no había que copiar.** Borrarlas después de
extraer: son estado efímero de otra máquina y otra versión de Moodle, y provocan fallos de
sesión y de caché difíciles de diagnosticar. Moodle las regenera solas al primer arranque.

```bash
sudo rm -rf /opt/moodledata/coipo_moodle/{cache,localcache,sessions,temp,trashdir,muc}
sudo rm -rf /opt/moodledata/coipo_moodle/{cgi-bin,home,models}   # venían vacías
sudo chown -R 33:33 /opt/moodledata/coipo_moodle
```

### Opción B — transferencia reanudable, sin archivo intermedio

Aprovecha que `filedir` está dividido en **256 subcarpetas** (`00`…`ff`): se transfiere una
por una y se anota cuál terminó, de modo que **si se corta la VPN se retoma sin repetir lo ya
copiado**. Además no necesita espacio extra en disco: va directo por la tubería.

```bash
SRV=usuario@172.31.2.41
cd ~   # el traslado.log se crea donde estés parado

# filedir — 256 carpetas, reanudable
for d in /c/moodledata/filedir/*/; do
  b=$(basename "$d")
  grep -qx "$b" traslado.log 2>/dev/null && continue      # ya copiada → saltar
  tar -cf - -C /c/moodledata/filedir "$b" \
    | ssh $SRV "sudo tar -xf - -C /opt/moodledata/coipo_moodle/filedir/" \
    && echo "$b" >> traslado.log \
    && echo "OK $b  ($(wc -l < traslado.log)/256)"
done

# paquetes de idioma (9,8 MB)
tar -cf - -C /c/moodledata lang \
  | ssh $SRV "sudo tar -xf - -C /opt/moodledata/coipo_moodle/"

# devolver la propiedad al usuario del contenedor
ssh $SRV 'sudo chown -R 33:33 /opt/moodledata/coipo_moodle'
```

Si se corta: vuelve a lanzar **el mismo bucle**. Continúa donde quedó.

### Qué NO se copia (y por qué)

Verificado carpeta por carpeta en el origen:

| Carpeta | Contenido real | Decisión |
|---|---|---|
| `cgi-bin/`, `home/`, `models/` | **vacías** (0 archivos) | no copiar |
| `trashdir/` | 218 archivos, 28 MB — papelera | no copiar |
| `cache/`, `localcache/`, `muc/` | cachés | se regeneran solas |
| `sessions/` | sesiones de usuarios del sitio viejo | no copiar: arrastra estado obsoleto |
| `temp/` | temporales | se regenera sola |
| `filedir/` | **10,84 GB — el contenido real de los cursos** | **copiar** |
| `lang/` | 9,8 MB — paquetes de idioma | **copiar** |

Copiar `cache/`, `sessions/` o `temp/` no solo desperdicia tiempo: provoca fallos de sesión
y de caché difíciles de diagnosticar después.

---

## 6. Verificar el traslado

No basta con que los comandos terminen sin error. Estos son los números medidos en el
origen; **deben coincidir**:

```bash
ssh $SRV 'find /opt/moodledata/coipo_moodle/filedir -type f | wc -l'
# → debe dar 15994

ssh $SRV 'du -sh /opt/moodledata/coipo_moodle/filedir'
# → debe dar ~11G

ssh $SRV 'ls -ld /opt/moodledata/coipo_moodle'
# → el dueño debe verse como 33:33 (o www-data si el host tiene ese usuario)

ssh $SRV 'ls -1 /opt/moodledata/coipo_moodle/filedir | wc -l'
# → debe dar 256

ssh $SRV 'ls -lh /opt/migracion/coipo_moodle/bitnami_moodle.sql'
# → debe dar ~295M
```

Si el conteo de archivos no llega a 15.994, faltan carpetas: relanza el bucle del paso 5
(saltará automáticamente las ya copiadas).

---

## 7. Cuando esto termine

El siguiente paso es `docs/MIGRACION.md`: construir la imagen, cargar el dump en un MariaDB
temporal, actualizar de Moodle 4.4.2 a 4.5.10 y migrar a PostgreSQL 17.

---

## Alternativa: instalar `rsync`

Si puedes instalar software (requiere permisos de administrador), `wsl --install` habilita
`rsync` real y el traslado queda reanudable de forma nativa:

```bash
rsync -avhP --exclude={cache,localcache,sessions,temp,trashdir,cgi-bin,home,models} \
  /mnt/c/c/moodledata/ usuario@172.31.2.41:/opt/moodledata/coipo_moodle/
```

Es más simple y más rápido que el bucle de `tar`. El bucle existe porque funciona **hoy**,
sin instalar nada.


Academia2026.
user
