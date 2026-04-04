# Rendición de gastos y viáticos

Aplicación para que los empleados carguen comprobantes (fotos), datos de la rendición y, opcionalmente, una explicación por voz. El backend automatiza el almacenamiento en **Google Drive**, el análisis con **OpenAI** (OCR por visión + política con **RAG** sobre el Manual de Ética), el registro en **Google Sheets**, el correo al gerente para **aprobar o rechazar** el lote, y la notificación al área contable.

**Repositorio:** [github.com/jugauna/rendicion_de_gastos](https://github.com/jugauna/rendicion_de_gastos)

---

## Qué incluye este repo

| Recurso | Descripción |
|--------|-------------|
| `Formulario.html` | Formulario front-end (empleado, motivo, N bloques de categoría/foto/observación, audio opcional). Envía por POST multipart al proxy. |
| `proxy-n8n.php` | Proxy PHP en el mismo dominio que el formulario: reenvía el multipart al webhook de n8n y envía el header `X-N8N-SECRET`. |
| `Aprobacion_de_gasto.html` | Página de referencia / plantilla relacionada con la aprobación (flujo principal vía mail + webhook de n8n). |
| `n8n-workflow-rendicion-gastos.json` | Workflow principal de **n8n** (versión **2.0**): Drive, IA, RAG del Manual de Ética, Sheets, mail gerente, Wait, contable, auditoría. |
| `n8n-workflow-audit-error-handler.json` | Workflow auxiliar de errores → hoja **Audit_Logs**. |
| `VERSION` | Número de versión publicado del paquete de entrega (HTML + proxy + export n8n). |

---

## Flujo general

1. El empleado completa el formulario y envía las imágenes de los comprobantes (y opcionalmente audio).
2. El **proxy** valida método POST, reenvía todo al **webhook de n8n** y adjunta el secreto compartido.
3. **n8n** valida el secreto, transcribe el audio con **Whisper** si hay archivo, responde al cliente con **200** y sigue en segundo plano.
4. Se indexa el **Manual de Ética** (texto embebido en el workflow) en un **vector store en memoria**, se crea o reutiliza la subcarpeta del empleado en **Drive** y se suben las fotos.
5. Por cada ticket: **visión (gpt-4o)** extrae montos, fecha, CUIT, etc., y un score de **legibilidad/coherencia del comprobante**.
6. Un **Question and Answer Chain** con **RAG** consulta el manual y devuelve un **Compliance_Score de política** y observaciones (prohibiciones, límites, coherencia con la categoría, etc.).
7. Se unifica la fila para **Sheets**: `Compliance_Score` global como mínimo entre score de OCR y score de ética; estado y observaciones según reglas (incl. montos altos y revisión manual).
8. Se envía el **correo al gerente** con enlaces de aprobación; al decidir, n8n **actualiza Sheets** y puede **notificar al contable** y registrar **Audit_Logs**.

El **Manual de Ética** no se lee desde Drive: va **incrustado** en el nodo `Code — Manual ética (KB)` del workflow. Drive se usa para las **fotos** y la estructura de carpetas de rendiciones.

---

## Requisitos

- **n8n** (instancia con nodos LangChain: vector store, embeddings, Question and Answer Chain, OpenAI).
- **Credencial OpenAI** (Whisper, visión/imagen, embeddings ×2, modelo chat del RAG).
- **Credenciales Google** para Drive y Sheets (mismo spreadsheet: hoja `Rendiciones`, hoja `Audit_Logs` según configuración).
- **PHP** en el hosting del sitio donde vive el formulario (para `proxy-n8n.php`), **no** un alojamiento estático que no ejecute PHP.

---

## Configuración destacada

### Proxy (`proxy-n8n.php`)

- URL del webhook de n8n (`$n8n_webhook_url`).
- Token compartido (`$secret_token`) alineado con **Variables — Config** del workflow y/o variable de entorno en n8n.
- Orígenes CORS si el HTML se sirve desde otro dominio.

### Workflow n8n (`Variables — Config`)

Incluye (entre otros): IDs de carpeta Drive y spreadsheet Sheets, correos `from` / gerente / jefe contable, sufijo del webhook de espera del gerente, `webhook_secret_provisorio`, y **`manual_etica_id`** (clave lógica del vector store in-memory compartida entre inserción y recuperación del manual).

Tras importar el JSON, hay que **asignar credenciales** en n8n a los nodos OpenAI y Google.

---

## Seguridad

- El webhook no debe quedar público sin el secreto: el proxy y/o el header `X-N8N-SECRET` deben coincidir con la política definida en el IF del workflow (en entornos de prueba puede haber bypass; en producción conviene exigir el header).

---

## Versión

Ver archivo **`VERSION`** en la raíz del repositorio. El workflow exportado en `n8n-workflow-rendicion-gastos.json` está pensado como **v2.0** (RAG + Manual de Ética embebido).

---

## Licencia y autor

Proyecto mantenido en el repositorio enlazado arriba. Ajustá nombres de empresa, dominios y credenciales antes de desplegar en producción.
