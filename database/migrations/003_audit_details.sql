-- Complementa instalações existentes com contexto seguro para auditoria.
ALTER TABLE audit_events ADD COLUMN message VARCHAR(500) NULL AFTER result;
ALTER TABLE audit_events ADD COLUMN ip_address VARCHAR(45) NULL AFTER message;
ALTER TABLE audit_events ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address;
ALTER TABLE audit_events ADD COLUMN request_id CHAR(32) NULL AFTER user_agent;
ALTER TABLE audit_events ADD KEY idx_audit_result_created (result, created_at);
ALTER TABLE audit_events ADD KEY idx_audit_entity (entity_type, entity_id);
