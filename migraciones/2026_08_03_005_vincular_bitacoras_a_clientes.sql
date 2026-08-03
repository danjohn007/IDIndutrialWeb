-- Migracion MySQL/MariaDB: vincular accesos Bitacora ID existentes al cliente.
-- Necesario para que un cliente vea varios proyectos en su portal.

UPDATE client_portal_users cpu
JOIN opportunities o ON o.id = cpu.opportunity_id
SET cpu.client_id = o.client_id
WHERE cpu.client_id IS NULL
  AND o.client_id IS NOT NULL;