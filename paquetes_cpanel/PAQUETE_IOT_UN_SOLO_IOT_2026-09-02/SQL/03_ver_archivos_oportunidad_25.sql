-- Revisa que archivos espera encontrar el CRM para la oportunidad 25.
-- Ejecutar en la base CRM: idindust_crm_idindustrial.

SELECT
  id,
  opportunity_id,
  file_path,
  original_name,
  mime,
  size,
  created_at
FROM opportunity_attachments
WHERE opportunity_id = 25
ORDER BY id;

-- La columna file_path es la ruta real relativa a public_html/IoT/crm/.
-- Ejemplo:
-- file_path = data/opportunity-attachments/abc123.pdf
-- Debe existir:
-- public_html/IoT/crm/data/opportunity-attachments/abc123.pdf
