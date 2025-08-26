
        CREATE TABLE IF NOT EXISTS app_user (
          id INT AUTO_INCREMENT PRIMARY KEY,
          email VARCHAR(180) NOT NULL UNIQUE,
          roles JSON NOT NULL,
          password VARCHAR(255) NULL,
          datatable_columns JSON NULL,
          grafana_dashboards JSON NULL,
          grafana_token VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS people (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(100) NOT NULL,
          email VARCHAR(180) NOT NULL,
          role VARCHAR(50) NOT NULL DEFAULT 'ROLE_USER'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS grafana_dashboard (
          id INT AUTO_INCREMENT PRIMARY KEY,
          uid VARCHAR(64) NOT NULL UNIQUE,
          slug VARCHAR(255) NOT NULL,
          title VARCHAR(255) NOT NULL,
          allowed_roles JSON NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        INSERT INTO people (name, email, role) VALUES
        ('Alice Admin','alice@example.com','ROLE_ADMIN'),
        ('Bob User','bob@example.com','ROLE_USER'),
        ('Carol User','carol@example.com','ROLE_USER');
        INSERT INTO app_user (email, roles, password, datatable_columns, grafana_dashboards, grafana_token) VALUES
        ('admin@example.com', JSON_ARRAY('ROLE_ADMIN'), NULL, JSON_ARRAY('id','name','email','role'), JSON_ARRAY('cpu','storage','network'), NULL),
        ('user@example.com', JSON_ARRAY('ROLE_USER'), NULL, JSON_ARRAY('id','name','email'), JSON_ARRAY('cpu'), NULL);
        INSERT INTO grafana_dashboard (uid, slug, title, allowed_roles) VALUES
        ('cpu', 'cpu-usage', 'CPU Usage', JSON_ARRAY('ROLE_ADMIN','ROLE_USER')),
        ('storage', 'storage-capacity', 'Storage Capacity', JSON_ARRAY('ROLE_ADMIN')),
        ('network', 'network-traffic', 'Network Traffic', JSON_ARRAY('ROLE_ADMIN'));
        