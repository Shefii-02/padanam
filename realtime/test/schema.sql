-- Minimal copy of the Laravel tables the realtime server touches (for the smoke test only).
DROP TABLE IF EXISTS call_participants, call_sessions, chat_messages, chat_members, chat_rooms, enrollments, model_has_roles, roles, users;
CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), phone VARCHAR(15), avatar VARCHAR(16), status ENUM('active','blocked') DEFAULT 'active', last_seen_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL);
CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), guard_name VARCHAR(255) DEFAULT 'api');
CREATE TABLE model_has_roles (role_id BIGINT UNSIGNED, model_type VARCHAR(255), model_id BIGINT UNSIGNED, PRIMARY KEY (role_id, model_id, model_type));
CREATE TABLE enrollments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED, batch_id BIGINT UNSIGNED, status ENUM('active','expired','revoked') DEFAULT 'active');
CREATE TABLE chat_rooms (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type ENUM('direct','group','batch_group','broadcast','staff'), name VARCHAR(255), avatar VARCHAR(16), description TEXT, batch_id BIGINT UNSIGNED NULL, direct_key VARCHAR(40) NULL UNIQUE, invite_code VARCHAR(16) NULL UNIQUE, invite_enabled TINYINT(1) DEFAULT 1, join_mode ENUM('open','approval','invite_only') DEFAULT 'approval', settings JSON, last_message_id BIGINT UNSIGNED NULL, last_message_at TIMESTAMP NULL, members_count INT UNSIGNED DEFAULT 0, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL);
CREATE TABLE chat_members (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, room_id BIGINT UNSIGNED, user_id BIGINT UNSIGNED, role ENUM('owner','admin','moderator','member') DEFAULT 'member', can_send TINYINT(1) NULL, muted_until TIMESTAMP NULL, notifications TINYINT(1) DEFAULT 1, pinned TINYINT(1) DEFAULT 0, last_read_message_id BIGINT UNSIGNED NULL, joined_at TIMESTAMP NULL, left_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE (room_id, user_id));
CREATE TABLE chat_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, room_id BIGINT UNSIGNED, user_id BIGINT UNSIGNED NULL, type ENUM('text','image','file','audio','poll','system') DEFAULT 'text', body TEXT NULL, meta JSON NULL, reply_to_id BIGINT UNSIGNED NULL, client_id VARCHAR(40) NULL, edited_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL, INDEX (room_id, id));
CREATE TABLE call_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, room_id BIGINT UNSIGNED NULL, type ENUM('voice','video','live'), mode ENUM('one_to_one','one_to_many','many_to_many'), sfu_room_id VARCHAR(255) NULL, started_by BIGINT UNSIGNED NULL, started_at TIMESTAMP NULL, ended_at TIMESTAMP NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL);
CREATE TABLE call_participants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, call_session_id BIGINT UNSIGNED, user_id BIGINT UNSIGNED, role ENUM('host','speaker','listener') DEFAULT 'listener', joined_at TIMESTAMP NULL, left_at TIMESTAMP NULL);

INSERT INTO users (id, name, avatar) VALUES (1,'Suresh Menon','👨‍🏫'), (2,'Arjun Krishnan','🧑‍🎓'), (3,'Meera S','👩‍🎓'), (4,'Blocked Guy',NULL);
UPDATE users SET status='blocked' WHERE id=4;
INSERT INTO roles (id, name) VALUES (1,'teacher'), (2,'student');
INSERT INTO model_has_roles VALUES (1,'user',1), (2,'user',2), (2,'user',3), (2,'user',4);
INSERT INTO enrollments (user_id, batch_id) VALUES (2, 7), (3, 7);
INSERT INTO chat_rooms (id, type, name, batch_id, settings, members_count) VALUES
  (10,'batch_group','LDC Morning',7,'{"who_can_send":"all","send_media":true,"send_links":false,"slow_mode_sec":0,"voice_enabled":true}',3),
  (11,'broadcast','Announcements',NULL,'{"who_can_send":"admins"}',3),
  (12,'direct',NULL,NULL,'{"send_links":true}',2),
  (13,'group','Slow room',NULL,'{"slow_mode_sec":30}',2);
INSERT INTO chat_members (room_id, user_id, role, joined_at) VALUES
  (10,1,'admin',NOW()), (10,2,'member',NOW()), (10,3,'member',NOW()),
  (11,1,'owner',NOW()), (11,2,'member',NOW()), (11,3,'member',NOW()),
  (12,1,'member',NOW()), (12,2,'member',NOW()),
  (13,2,'member',NOW()), (13,3,'member',NOW());
