<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250911104528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE eav.attribute (attribute_id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, data_type VARCHAR(16) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, tenant_id BIGINT NOT NULL, INDEX IDX_F42F5F2E9033212A (tenant_id), PRIMARY KEY (attribute_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.eav_relation (rel_id BIGINT AUTO_INCREMENT NOT NULL, valid_from DATETIME DEFAULT NULL, valid_to DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, tenant_id BIGINT NOT NULL, rel_type_id BIGINT NOT NULL, parent_entity_id BIGINT NOT NULL, child_entity_id BIGINT NOT NULL, INDEX IDX_7FEF8BC19033212A (tenant_id), INDEX IDX_7FEF8BC1947655DD (rel_type_id), INDEX IDX_7FEF8BC1706E52B3 (parent_entity_id), INDEX IDX_7FEF8BC150141DD1 (child_entity_id), PRIMARY KEY (rel_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.eav_relation_type (rel_type_id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, is_directed TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, tenant_id BIGINT NOT NULL, INDEX IDX_35CF25C79033212A (tenant_id), PRIMARY KEY (rel_type_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.eav_value (value_id BIGINT AUTO_INCREMENT NOT NULL, value_string LONGTEXT DEFAULT NULL, value_integer BIGINT DEFAULT NULL, value_decimal NUMERIC(20, 6) DEFAULT NULL, value_boolean TINYINT(1) DEFAULT NULL, value_datetime DATETIME DEFAULT NULL, value_json JSON DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, entity_id BIGINT NOT NULL, attribute_id BIGINT NOT NULL, INDEX IDX_4BDC30AF81257D5D (entity_id), INDEX IDX_4BDC30AFB6E62EFA (attribute_id), PRIMARY KEY (value_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.entity (entity_id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, tenant_id BIGINT NOT NULL, type_id BIGINT NOT NULL, INDEX IDX_D50B0DB89033212A (tenant_id), INDEX IDX_D50B0DB8C54C8C93 (type_id), PRIMARY KEY (entity_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.entity_type (type_id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, tenant_id BIGINT NOT NULL, INDEX IDX_F33C62819033212A (tenant_id), PRIMARY KEY (type_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.tenant (tenant_id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (tenant_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE eav.type_attribute (id BIGINT AUTO_INCREMENT NOT NULL, is_required TINYINT(1) DEFAULT 0 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, type_id BIGINT NOT NULL, attribute_id BIGINT NOT NULL, INDEX IDX_E62FD18DC54C8C93 (type_id), INDEX IDX_E62FD18DB6E62EFA (attribute_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE eav.attribute ADD CONSTRAINT FK_F42F5F2E9033212A FOREIGN KEY (tenant_id) REFERENCES eav.tenant (tenant_id)');
        $this->addSql('ALTER TABLE eav.eav_relation ADD CONSTRAINT FK_7FEF8BC19033212A FOREIGN KEY (tenant_id) REFERENCES eav.tenant (tenant_id)');
        $this->addSql('ALTER TABLE eav.eav_relation ADD CONSTRAINT FK_7FEF8BC1947655DD FOREIGN KEY (rel_type_id) REFERENCES eav.eav_relation_type (rel_type_id)');
        $this->addSql('ALTER TABLE eav.eav_relation ADD CONSTRAINT FK_7FEF8BC1706E52B3 FOREIGN KEY (parent_entity_id) REFERENCES eav.entity (entity_id)');
        $this->addSql('ALTER TABLE eav.eav_relation ADD CONSTRAINT FK_7FEF8BC150141DD1 FOREIGN KEY (child_entity_id) REFERENCES eav.entity (entity_id)');
        $this->addSql('ALTER TABLE eav.eav_relation_type ADD CONSTRAINT FK_35CF25C79033212A FOREIGN KEY (tenant_id) REFERENCES eav.tenant (tenant_id)');
        $this->addSql('ALTER TABLE eav.eav_value ADD CONSTRAINT FK_4BDC30AF81257D5D FOREIGN KEY (entity_id) REFERENCES eav.entity (entity_id)');
        $this->addSql('ALTER TABLE eav.eav_value ADD CONSTRAINT FK_4BDC30AFB6E62EFA FOREIGN KEY (attribute_id) REFERENCES eav.attribute (attribute_id)');
        $this->addSql('ALTER TABLE eav.entity ADD CONSTRAINT FK_D50B0DB89033212A FOREIGN KEY (tenant_id) REFERENCES eav.tenant (tenant_id)');
        $this->addSql('ALTER TABLE eav.entity ADD CONSTRAINT FK_D50B0DB8C54C8C93 FOREIGN KEY (type_id) REFERENCES eav.entity_type (type_id)');
        $this->addSql('ALTER TABLE eav.entity_type ADD CONSTRAINT FK_F33C62819033212A FOREIGN KEY (tenant_id) REFERENCES eav.tenant (tenant_id)');
        $this->addSql('ALTER TABLE eav.type_attribute ADD CONSTRAINT FK_E62FD18DC54C8C93 FOREIGN KEY (type_id) REFERENCES eav.entity_type (type_id)');
        $this->addSql('ALTER TABLE eav.type_attribute ADD CONSTRAINT FK_E62FD18DB6E62EFA FOREIGN KEY (attribute_id) REFERENCES eav.attribute (attribute_id)');
        $this->addSql('ALTER TABLE fx_rate DROP FOREIGN KEY `fk_fx_from`');
        $this->addSql('ALTER TABLE fx_rate DROP FOREIGN KEY `fk_fx_to`');
        $this->addSql('ALTER TABLE ui_widget_instance DROP FOREIGN KEY `fk_widget_tab`');
        $this->addSql('DROP TABLE currency');
        $this->addSql('DROP TABLE fx_rate');
        $this->addSql('DROP TABLE google_events_map');
        $this->addSql('DROP TABLE google_oauth_tokens');
        $this->addSql('DROP TABLE price_quote');
        $this->addSql('DROP TABLE ui_widget_instance');
        $this->addSql('DROP TABLE vat_rate');
        $this->addSql('DROP INDEX email ON distribution_member');
        $this->addSql('ALTER TABLE distribution_member CHANGE is_active is_active TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE distribution_member RENAME INDEX list_id TO IDX_144356723DAE168B');
        $this->addSql('DROP INDEX idx_table_public ON dt_saved_filter');
        $this->addSql('DROP INDEX idx_table_owner ON dt_saved_filter');
        $this->addSql('ALTER TABLE dt_saved_filter CHANGE is_public is_public TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX report_id ON mail_attachment');
        $this->addSql('ALTER TABLE mail_attachment CHANGE type TYPE VARCHAR(16) NOT NULL, CHANGE format FORMAT VARCHAR(16) DEFAULT \'csv\' NOT NULL, CHANGE is_link is_link TINYINT(1) NOT NULL, CHANGE is_public_link is_public_link TINYINT(1) NOT NULL, CHANGE position POSITION INT NOT NULL');
        $this->addSql('ALTER TABLE mail_attachment RENAME INDEX template_id TO IDX_AD9C33475DA0FB8');
        $this->addSql('DROP INDEX scheduled_at ON mail_job');
        $this->addSql('DROP INDEX status ON mail_job');
        $this->addSql('ALTER TABLE mail_job CHANGE status STATUS VARCHAR(16) NOT NULL, CHANGE error_message error_message LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mail_job RENAME INDEX fk_job_template TO IDX_E7C3CF925DA0FB8');
        $this->addSql('DROP INDEX next_run_at ON mail_schedule');
        $this->addSql('DROP INDEX enabled ON mail_schedule');
        $this->addSql('ALTER TABLE mail_schedule CHANGE timezone timezone VARCHAR(64) NOT NULL, CHANGE minute MINUTE INT NOT NULL, CHANGE enabled enabled TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE mail_schedule RENAME INDEX template_id TO IDX_87EFD0B35DA0FB8');
        $this->addSql('ALTER TABLE mail_template CHANGE is_active is_active TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE mail_template_distribution_list DROP FOREIGN KEY `fk_tpl_list_list`');
        $this->addSql('ALTER TABLE mail_template_distribution_list DROP FOREIGN KEY `fk_tpl_list_template`');
        $this->addSql('ALTER TABLE mail_template_distribution_list ADD CONSTRAINT FK_25B6BE3D5DA0FB8 FOREIGN KEY (template_id) REFERENCES mail_template (id)');
        $this->addSql('ALTER TABLE mail_template_distribution_list ADD CONSTRAINT FK_25B6BE3D3DAE168B FOREIGN KEY (list_id) REFERENCES distribution_list (id)');
        $this->addSql('ALTER TABLE mail_template_distribution_list RENAME INDEX fk_tpl_list_list TO IDX_25B6BE3D3DAE168B');
        $this->addSql('DROP INDEX token_2 ON share_link');
        $this->addSql('DROP INDEX expires_at ON share_link');
        $this->addSql('ALTER TABLE share_link CHANGE is_public is_public TINYINT(1) NOT NULL, CHANGE format FORMAT VARCHAR(16) NOT NULL');
        $this->addSql('ALTER TABLE share_link RENAME INDEX token TO UNIQ_8B6B94685F37A13B');
        $this->addSql('DROP INDEX uniq_system_code ON ui_widget_tab');
        $this->addSql('DROP INDEX uniq_owner_title ON ui_widget_tab');
        $this->addSql('ALTER TABLE ui_widget_tab CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE currency (code CHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, name VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fx_rate (as_of_date DATE NOT NULL, from_ccy CHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, to_ccy CHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, rate NUMERIC(18, 8) NOT NULL, source VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX fk_fx_from (from_ccy), INDEX fk_fx_to (to_ccy), PRIMARY KEY (as_of_date, from_ccy, to_ccy)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE google_events_map (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, calendar_id VARCHAR(255) CHARACTER SET latin1 DEFAULT \'primary\' NOT NULL COLLATE `latin1_swedish_ci`, google_event_id VARCHAR(255) CHARACTER SET latin1 NOT NULL COLLATE `latin1_swedish_ci`, etag VARCHAR(255) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, updated_at_utc DATETIME DEFAULT NULL, appointment_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX uniq_user_cal_event (user_id, calendar_id, google_event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET latin1 COLLATE `latin1_swedish_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE google_oauth_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, access_token TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, refresh_token TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, expires_at INT NOT NULL, scope TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, token_type VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX uniq_google_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE price_quote (quote_id BIGINT AUTO_INCREMENT NOT NULL, quote_ts DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, request_json JSON NOT NULL, result_json JSON NOT NULL, customer_ref VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tenant VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, who VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (quote_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ui_widget_instance (id INT AUTO_INCREMENT NOT NULL, tab_id INT NOT NULL, widget_type VARCHAR(80) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, config_json JSON NOT NULL, position INT DEFAULT 0 NOT NULL, x INT DEFAULT 0 NOT NULL, y INT DEFAULT 0 NOT NULL, w INT DEFAULT 4 NOT NULL, h INT DEFAULT 3 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX fk_widget_tab (tab_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE vat_rate (country_code CHAR(2) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, category VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT \'standard\' NOT NULL COLLATE `utf8mb4_general_ci`, valid_from DATE NOT NULL, valid_to DATE DEFAULT NULL, rate_pct NUMERIC(6, 3) NOT NULL, notes VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (country_code, category, valid_from)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE fx_rate ADD CONSTRAINT `fk_fx_from` FOREIGN KEY (from_ccy) REFERENCES currency (code)');
        $this->addSql('ALTER TABLE fx_rate ADD CONSTRAINT `fk_fx_to` FOREIGN KEY (to_ccy) REFERENCES currency (code)');
        $this->addSql('ALTER TABLE ui_widget_instance ADD CONSTRAINT `fk_widget_tab` FOREIGN KEY (tab_id) REFERENCES ui_widget_tab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE eav.attribute DROP FOREIGN KEY FK_F42F5F2E9033212A');
        $this->addSql('ALTER TABLE eav.eav_relation DROP FOREIGN KEY FK_7FEF8BC19033212A');
        $this->addSql('ALTER TABLE eav.eav_relation DROP FOREIGN KEY FK_7FEF8BC1947655DD');
        $this->addSql('ALTER TABLE eav.eav_relation DROP FOREIGN KEY FK_7FEF8BC1706E52B3');
        $this->addSql('ALTER TABLE eav.eav_relation DROP FOREIGN KEY FK_7FEF8BC150141DD1');
        $this->addSql('ALTER TABLE eav.eav_relation_type DROP FOREIGN KEY FK_35CF25C79033212A');
        $this->addSql('ALTER TABLE eav.eav_value DROP FOREIGN KEY FK_4BDC30AF81257D5D');
        $this->addSql('ALTER TABLE eav.eav_value DROP FOREIGN KEY FK_4BDC30AFB6E62EFA');
        $this->addSql('ALTER TABLE eav.entity DROP FOREIGN KEY FK_D50B0DB89033212A');
        $this->addSql('ALTER TABLE eav.entity DROP FOREIGN KEY FK_D50B0DB8C54C8C93');
        $this->addSql('ALTER TABLE eav.entity_type DROP FOREIGN KEY FK_F33C62819033212A');
        $this->addSql('ALTER TABLE eav.type_attribute DROP FOREIGN KEY FK_E62FD18DC54C8C93');
        $this->addSql('ALTER TABLE eav.type_attribute DROP FOREIGN KEY FK_E62FD18DB6E62EFA');
        $this->addSql('DROP TABLE eav.attribute');
        $this->addSql('DROP TABLE eav.eav_relation');
        $this->addSql('DROP TABLE eav.eav_relation_type');
        $this->addSql('DROP TABLE eav.eav_value');
        $this->addSql('DROP TABLE eav.entity');
        $this->addSql('DROP TABLE eav.entity_type');
        $this->addSql('DROP TABLE eav.tenant');
        $this->addSql('DROP TABLE eav.type_attribute');
        $this->addSql('ALTER TABLE distribution_member CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX email ON distribution_member (email)');
        $this->addSql('ALTER TABLE distribution_member RENAME INDEX idx_144356723dae168b TO list_id');
        $this->addSql('ALTER TABLE dt_saved_filter CHANGE is_public is_public TINYINT(1) DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_table_public ON dt_saved_filter (table_key, is_public)');
        $this->addSql('CREATE INDEX idx_table_owner ON dt_saved_filter (table_key, owner_id)');
        $this->addSql('ALTER TABLE mail_attachment CHANGE TYPE type ENUM(\'report\', \'file\') NOT NULL, CHANGE FORMAT format ENUM(\'csv\', \'excel\', \'json\') DEFAULT \'csv\', CHANGE is_link is_link TINYINT(1) DEFAULT 0 NOT NULL, CHANGE is_public_link is_public_link TINYINT(1) DEFAULT 0 NOT NULL, CHANGE POSITION position INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX report_id ON mail_attachment (report_id)');
        $this->addSql('ALTER TABLE mail_attachment RENAME INDEX idx_ad9c33475da0fb8 TO template_id');
        $this->addSql('ALTER TABLE mail_job CHANGE STATUS status ENUM(\'queued\', \'running\', \'sent\', \'failed\') DEFAULT \'queued\' NOT NULL, CHANGE error_message error_message TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX scheduled_at ON mail_job (scheduled_at)');
        $this->addSql('CREATE INDEX status ON mail_job (status)');
        $this->addSql('ALTER TABLE mail_job RENAME INDEX idx_e7c3cf925da0fb8 TO fk_job_template');
        $this->addSql('ALTER TABLE mail_schedule CHANGE timezone timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL, CHANGE MINUTE minute INT DEFAULT 0 NOT NULL, CHANGE enabled enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX next_run_at ON mail_schedule (next_run_at)');
        $this->addSql('CREATE INDEX enabled ON mail_schedule (enabled)');
        $this->addSql('ALTER TABLE mail_schedule RENAME INDEX idx_87efd0b35da0fb8 TO template_id');
        $this->addSql('ALTER TABLE mail_template CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE mail_template_distribution_list DROP FOREIGN KEY FK_25B6BE3D5DA0FB8');
        $this->addSql('ALTER TABLE mail_template_distribution_list DROP FOREIGN KEY FK_25B6BE3D3DAE168B');
        $this->addSql('ALTER TABLE mail_template_distribution_list ADD CONSTRAINT `fk_tpl_list_list` FOREIGN KEY (list_id) REFERENCES distribution_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_template_distribution_list ADD CONSTRAINT `fk_tpl_list_template` FOREIGN KEY (template_id) REFERENCES mail_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_template_distribution_list RENAME INDEX idx_25b6be3d3dae168b TO fk_tpl_list_list');
        $this->addSql('ALTER TABLE share_link CHANGE is_public is_public TINYINT(1) DEFAULT 1 NOT NULL, CHANGE FORMAT format ENUM(\'csv\', \'excel\', \'json\') DEFAULT \'csv\'');
        $this->addSql('CREATE INDEX token_2 ON share_link (token)');
        $this->addSql('CREATE INDEX expires_at ON share_link (expires_at)');
        $this->addSql('ALTER TABLE share_link RENAME INDEX uniq_8b6b94685f37a13b TO token');
        $this->addSql('ALTER TABLE ui_widget_tab CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_system_code ON ui_widget_tab (code, is_system)');
        $this->addSql('CREATE UNIQUE INDEX uniq_owner_title ON ui_widget_tab (owner_user_id, title)');
    }
}
