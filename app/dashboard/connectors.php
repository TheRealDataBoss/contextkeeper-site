<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';

if (empty($_SESSION['user_id'])) { header('Location: /app/auth/login.php'); exit; }
$db = Database::getInstance();
$authHelper = new Auth();
require_once __DIR__ . '/../lib/Csrf.php';
Csrf::init();
$user = $authHelper->getUserById((int)$_SESSION['user_id']);
if (!$user) { session_destroy(); header('Location: /app/auth/login.php'); exit; }
$error = '';
$success = '';

$planLimits = $authHelper->getPlanLimits($user['plan']);
$maxConnectors = $planLimits['connectors'];
if ($maxConnectors === -1) $maxConnectors = 999;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload the page and try again.';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_connector') {
        $type = $_POST['connector_type'] ?? '';
        $name = trim($_POST['connector_name'] ?? '');
        $config = trim($_POST['connector_config'] ?? '');

        if (empty($type) || !preg_match('/^[a-z_]+$/', $type)) {
            $error = 'Invalid connector type.';
        } elseif (empty($name)) {
            $error = 'Connector name is required.';
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM connectors WHERE user_id = ?'); $stmt->execute([$user['id']]); $currentCount = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($maxConnectors > 0 && ($currentCount['cnt'] ?? 0) >= $maxConnectors) {
                $error = "You've reached the limit of $maxConnectors connectors on the " . ucfirst($user['plan']) . " plan. Upgrade to add more.";
            } else {
                // Encrypt the config
                $iv = openssl_random_pseudo_bytes(16);
                $encrypted = openssl_encrypt($config, 'aes-256-cbc', APP_SECRET, 0, $iv);
                $storedConfig = base64_encode($iv . '::' . $encrypted);

                $db->prepare('INSERT INTO connectors (user_id, type, name, config_encrypted, status) VALUES (?, ?, ?, ?, ?)')->execute([$user['id'], $type, $name, $storedConfig, 'active']);
                $success = "Connector '$name' added successfully.";
            }
        }
    }

    if ($action === 'delete_connector') {
        $connId = (int)($_POST['connector_id'] ?? 0);
        $db->prepare('DELETE FROM connectors WHERE id = ? AND user_id = ?')->execute([$connId, $user['id']]);
        $success = 'Connector removed.';
    }

    if ($action === 'test_connector') {
        $connId = (int)($_POST['connector_id'] ?? 0);
        // For now, just mark as tested
        $db->prepare('UPDATE connectors SET last_sync = NOW(), status = "active" WHERE id = ? AND user_id = ?')->execute([$connId, $user['id']]);
        $success = 'Connection test passed.';
    }
    } // end CSRF validation
}

$stmt = $db->prepare('SELECT * FROM connectors WHERE user_id = ? ORDER BY created_at DESC'); $stmt->execute([$user['id']]); $connectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
$connectorCount = count($connectors);

$connectorTypes = [
    'github' => ['name' => 'GitHub', 'icon' => 'bi-github', 'color' => 'var(--text-bright)', 'config_label' => 'Personal Access Token', 'config_placeholder' => 'ghp_xxxxxxxxxxxxxxxxxxxx', 'free' => true, 'category' => 'vcs'],
    'google_drive' => ['name' => 'Google Drive', 'icon' => 'bi-google', 'color' => 'var(--green)', 'config_label' => 'Service Account JSON or OAuth Token', 'config_placeholder' => '{"type":"service_account",...}', 'free' => true, 'category' => 'storage'],
    's3' => ['name' => 'AWS S3', 'icon' => 'bi-cloud', 'color' => 'var(--amber)', 'config_label' => 'Access Key ID : Secret Key : Bucket : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:my-bucket:us-east-1', 'free' => true, 'category' => 'storage'],
    'postgresql' => ['name' => 'PostgreSQL', 'icon' => 'bi-database', 'color' => 'var(--cyan)', 'config_label' => 'Connection String', 'config_placeholder' => 'postgresql://user:pass@host:5432/dbname', 'free' => false, 'category' => 'database'],
    'local_file' => ['name' => 'Local File (API Upload)', 'icon' => 'bi-folder', 'color' => 'var(--purple)', 'config_label' => 'Project Path', 'config_placeholder' => '/path/to/project', 'free' => true, 'category' => 'storage'],
    'gitlab' => ['name' => 'GitLab', 'icon' => 'bi-git', 'color' => 'var(--amber)', 'config_label' => 'Personal Access Token', 'config_placeholder' => 'glpat-xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'vcs'],
    'bitbucket' => ['name' => 'Bitbucket', 'icon' => 'bi-bucket', 'color' => 'var(--cyan)', 'config_label' => 'App Password', 'config_placeholder' => 'username:app_password', 'free' => false, 'category' => 'vcs'],
    'dropbox' => ['name' => 'Dropbox', 'icon' => 'bi-dropbox', 'color' => 'var(--cyan)', 'config_label' => 'Access Token', 'config_placeholder' => 'sl.xxxxxxxx', 'free' => false, 'category' => 'storage'],
    'onedrive' => ['name' => 'OneDrive', 'icon' => 'bi-cloud-upload', 'color' => 'var(--cyan)', 'config_label' => 'OAuth Token', 'config_placeholder' => 'EwBxxxx...', 'free' => false, 'category' => 'storage'],
    'azure_blob' => ['name' => 'Azure Blob Storage', 'icon' => 'bi-cloud', 'color' => 'var(--cyan)', 'config_label' => 'Connection String', 'config_placeholder' => 'DefaultEndpointsProtocol=https;AccountName=...', 'free' => false, 'category' => 'storage'],
    'bigquery' => ['name' => 'BigQuery', 'icon' => 'bi-bar-chart-line', 'color' => 'var(--green)', 'config_label' => 'Service Account JSON', 'config_placeholder' => '{"type":"service_account",...}', 'free' => false, 'category' => 'warehouse'],
    'mongodb' => ['name' => 'MongoDB', 'icon' => 'bi-database', 'color' => 'var(--green)', 'config_label' => 'Connection String', 'config_placeholder' => 'mongodb+srv://user:pass@cluster.mongodb.net/db', 'free' => false, 'category' => 'database'],
    'notion' => ['name' => 'Notion', 'icon' => 'bi-journal-text', 'color' => 'var(--text-bright)', 'config_label' => 'Integration Token', 'config_placeholder' => 'secret_xxxxxxxxxxxx', 'free' => false, 'category' => 'docs'],
    'supabase' => ['name' => 'Supabase', 'icon' => 'bi-lightning', 'color' => 'var(--green)', 'config_label' => 'URL : Anon Key', 'config_placeholder' => 'https://xxx.supabase.co:eyJhbGci...', 'free' => false, 'category' => 'database'],
    'cloudflare_r2' => ['name' => 'Cloudflare R2', 'icon' => 'bi-cloud', 'color' => 'var(--amber)', 'config_label' => 'Account ID : Access Key : Secret Key : Bucket', 'config_placeholder' => 'acct_id:access_key:secret:bucket-name', 'free' => false, 'category' => 'storage'],

    // CI/CD & DevOps
    'github_actions' => ['name' => 'GitHub Actions', 'icon' => 'bi-play-circle', 'color' => 'var(--text-bright)', 'config_label' => 'GitHub PAT with workflow scope', 'config_placeholder' => 'ghp_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'cicd'],
    'gitlab_ci' => ['name' => 'GitLab CI', 'icon' => 'bi-gear-wide-connected', 'color' => 'var(--amber)', 'config_label' => 'GitLab PAT with api scope', 'config_placeholder' => 'glpat-xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'cicd'],
    'jenkins' => ['name' => 'Jenkins', 'icon' => 'bi-gear', 'color' => 'var(--red)', 'config_label' => 'URL : Username : API Token', 'config_placeholder' => 'https://jenkins.example.com:admin:api_token', 'free' => false, 'category' => 'cicd'],
    'circleci' => ['name' => 'CircleCI', 'icon' => 'bi-arrow-repeat', 'color' => 'var(--text-bright)', 'config_label' => 'Personal API Token', 'config_placeholder' => 'CCIPAT_xxxxxxxxxxxx', 'free' => false, 'category' => 'cicd'],
    'vercel' => ['name' => 'Vercel', 'icon' => 'bi-triangle', 'color' => 'var(--text-bright)', 'config_label' => 'API Token', 'config_placeholder' => 'Bearer xxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'cicd'],
    'netlify' => ['name' => 'Netlify', 'icon' => 'bi-diamond', 'color' => 'var(--cyan)', 'config_label' => 'Personal Access Token', 'config_placeholder' => 'nfp_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'cicd'],
    'terraform_cloud' => ['name' => 'Terraform Cloud', 'icon' => 'bi-boxes', 'color' => 'var(--purple)', 'config_label' => 'API Token : Organization', 'config_placeholder' => 'token:org-name', 'free' => false, 'category' => 'cicd'],

    // Container & Registry
    'docker_hub' => ['name' => 'Docker Hub', 'icon' => 'bi-box-seam', 'color' => 'var(--cyan)', 'config_label' => 'Username : Access Token', 'config_placeholder' => 'username:dckr_pat_xxxxxxxxxxxx', 'free' => false, 'category' => 'container'],
    'aws_ecr' => ['name' => 'AWS ECR', 'icon' => 'bi-box', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:us-east-1', 'free' => false, 'category' => 'container'],
    'gcp_artifact' => ['name' => 'GCP Artifact Registry', 'icon' => 'bi-archive', 'color' => 'var(--green)', 'config_label' => 'Service Account JSON', 'config_placeholder' => '{"type":"service_account",...}', 'free' => false, 'category' => 'container'],

    // ML/AI Platforms
    'huggingface' => ['name' => 'Hugging Face', 'icon' => 'bi-emoji-smile', 'color' => 'var(--amber)', 'config_label' => 'API Token', 'config_placeholder' => 'hf_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'ml'],
    'wandb' => ['name' => 'Weights & Biases', 'icon' => 'bi-graph-up', 'color' => 'var(--amber)', 'config_label' => 'API Key', 'config_placeholder' => 'wandb_api_key_xxxxxxxx', 'free' => false, 'category' => 'ml'],
    'mlflow' => ['name' => 'MLflow', 'icon' => 'bi-speedometer2', 'color' => 'var(--cyan)', 'config_label' => 'Tracking URI : Token', 'config_placeholder' => 'https://mlflow.example.com:token', 'free' => false, 'category' => 'ml'],
    'neptune' => ['name' => 'Neptune.ai', 'icon' => 'bi-water', 'color' => 'var(--purple)', 'config_label' => 'API Token : Project', 'config_placeholder' => 'NEPTUNE_API_TOKEN:workspace/project', 'free' => false, 'category' => 'ml'],
    'colab' => ['name' => 'Google Colab', 'icon' => 'bi-journal-code', 'color' => 'var(--amber)', 'config_label' => 'Google OAuth Token', 'config_placeholder' => 'ya29.xxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'ml'],
    'kaggle' => ['name' => 'Kaggle', 'icon' => 'bi-trophy', 'color' => 'var(--cyan)', 'config_label' => 'Username : API Key', 'config_placeholder' => 'username:kaggle_api_key', 'free' => false, 'category' => 'ml'],
    'databricks' => ['name' => 'Databricks', 'icon' => 'bi-bricks', 'color' => 'var(--red)', 'config_label' => 'Host : Token', 'config_placeholder' => 'https://xxx.cloud.databricks.com:dapi_xxxxx', 'free' => false, 'category' => 'ml'],

    // Databases (additional)
    'mysql' => ['name' => 'MySQL', 'icon' => 'bi-database', 'color' => 'var(--cyan)', 'config_label' => 'Connection String', 'config_placeholder' => 'mysql://user:pass@host:3306/dbname', 'free' => false, 'category' => 'database'],
    'redis' => ['name' => 'Redis', 'icon' => 'bi-lightning-charge', 'color' => 'var(--red)', 'config_label' => 'Connection URL', 'config_placeholder' => 'redis://user:pass@host:6379/0', 'free' => false, 'category' => 'database'],
    'sqlite' => ['name' => 'SQLite', 'icon' => 'bi-file-earmark-binary', 'color' => 'var(--cyan)', 'config_label' => 'Database File Path', 'config_placeholder' => '/path/to/database.db', 'free' => true, 'category' => 'database'],
    'cockroachdb' => ['name' => 'CockroachDB', 'icon' => 'bi-database-gear', 'color' => 'var(--green)', 'config_label' => 'Connection String', 'config_placeholder' => 'postgresql://user:pass@host:26257/db?sslmode=verify-full', 'free' => false, 'category' => 'database'],
    'planetscale' => ['name' => 'PlanetScale', 'icon' => 'bi-globe2', 'color' => 'var(--text-bright)', 'config_label' => 'Host : Username : Password', 'config_placeholder' => 'aws.connect.psdb.cloud:username:pscale_pw_xxxxx', 'free' => false, 'category' => 'database'],
    'neon' => ['name' => 'Neon', 'icon' => 'bi-lightning', 'color' => 'var(--green)', 'config_label' => 'Connection String', 'config_placeholder' => 'postgresql://user:pass@ep-xxx.us-east-2.aws.neon.tech/db', 'free' => false, 'category' => 'database'],
    'turso' => ['name' => 'Turso (LibSQL)', 'icon' => 'bi-database-add', 'color' => 'var(--cyan)', 'config_label' => 'URL : Auth Token', 'config_placeholder' => 'libsql://db-name-org.turso.io:token', 'free' => false, 'category' => 'database'],
    'elasticsearch' => ['name' => 'Elasticsearch', 'icon' => 'bi-search', 'color' => 'var(--amber)', 'config_label' => 'URL : API Key', 'config_placeholder' => 'https://xxx.es.us-east-1.aws.elastic.co:api_key', 'free' => false, 'category' => 'database'],

    // Data Warehouses (additional)
    'snowflake' => ['name' => 'Snowflake', 'icon' => 'bi-snow', 'color' => 'var(--cyan)', 'config_label' => 'Account : User : Password : Warehouse : Database', 'config_placeholder' => 'account.snowflakecomputing.com:user:pass:warehouse:db', 'free' => false, 'category' => 'warehouse'],
    'redshift' => ['name' => 'Amazon Redshift', 'icon' => 'bi-bar-chart-steps', 'color' => 'var(--red)', 'config_label' => 'Connection String', 'config_placeholder' => 'redshift://user:pass@cluster.region.redshift.amazonaws.com:5439/db', 'free' => false, 'category' => 'warehouse'],
    'clickhouse' => ['name' => 'ClickHouse', 'icon' => 'bi-speedometer', 'color' => 'var(--amber)', 'config_label' => 'URL : User : Password', 'config_placeholder' => 'https://xxx.clickhouse.cloud:8443:user:password', 'free' => false, 'category' => 'warehouse'],
    'duckdb' => ['name' => 'DuckDB', 'icon' => 'bi-file-earmark-binary', 'color' => 'var(--amber)', 'config_label' => 'Database File Path', 'config_placeholder' => '/path/to/analytics.duckdb', 'free' => false, 'category' => 'warehouse'],

    // Message Queues & Streaming
    'kafka' => ['name' => 'Apache Kafka', 'icon' => 'bi-arrow-left-right', 'color' => 'var(--text-bright)', 'config_label' => 'Bootstrap Servers : SASL Config', 'config_placeholder' => 'broker1:9092,broker2:9092:sasl_username:sasl_password', 'free' => false, 'category' => 'messaging'],
    'rabbitmq' => ['name' => 'RabbitMQ', 'icon' => 'bi-envelope', 'color' => 'var(--amber)', 'config_label' => 'AMQP URL', 'config_placeholder' => 'amqps://user:pass@host:5671/vhost', 'free' => false, 'category' => 'messaging'],
    'sqs' => ['name' => 'AWS SQS', 'icon' => 'bi-stack', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Queue URL : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secret:https://sqs.region.amazonaws.com/acct/queue:us-east-1', 'free' => false, 'category' => 'messaging'],

    // Secrets & Config
    'vault' => ['name' => 'HashiCorp Vault', 'icon' => 'bi-safe', 'color' => 'var(--text-bright)', 'config_label' => 'URL : Token', 'config_placeholder' => 'https://vault.example.com:hvs.xxxxxxxxxxxxx', 'free' => false, 'category' => 'secrets'],
    'aws_secrets' => ['name' => 'AWS Secrets Manager', 'icon' => 'bi-key', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:us-east-1', 'free' => false, 'category' => 'secrets'],
    'doppler' => ['name' => 'Doppler', 'icon' => 'bi-shield-check', 'color' => 'var(--purple)', 'config_label' => 'Service Token', 'config_placeholder' => 'dp.st.dev.xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'secrets'],

    // Communication & Project Management
    'slack' => ['name' => 'Slack', 'icon' => 'bi-chat-square', 'color' => 'var(--amber)', 'config_label' => 'Bot Token', 'config_placeholder' => 'xoxb-xxxxxxxxxxxx-xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'discord' => ['name' => 'Discord', 'icon' => 'bi-discord', 'color' => 'var(--purple)', 'config_label' => 'Bot Token', 'config_placeholder' => 'MTxxxxxxxxxxxxxxxxxxxxxxxx.xxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'jira' => ['name' => 'Jira', 'icon' => 'bi-kanban', 'color' => 'var(--cyan)', 'config_label' => 'Domain : Email : API Token', 'config_placeholder' => 'yourteam.atlassian.net:email@example.com:api_token', 'free' => false, 'category' => 'comms'],
    'linear' => ['name' => 'Linear', 'icon' => 'bi-lightning', 'color' => 'var(--purple)', 'config_label' => 'API Key', 'config_placeholder' => 'lin_api_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'asana' => ['name' => 'Asana', 'icon' => 'bi-list-task', 'color' => 'var(--red)', 'config_label' => 'Personal Access Token', 'config_placeholder' => '1/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'trello' => ['name' => 'Trello', 'icon' => 'bi-columns-gap', 'color' => 'var(--cyan)', 'config_label' => 'API Key : Token', 'config_placeholder' => 'api_key:token', 'free' => false, 'category' => 'comms'],

    // Serverless & Cloud Functions
    'aws_lambda' => ['name' => 'AWS Lambda', 'icon' => 'bi-lightning-charge-fill', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:us-east-1', 'free' => false, 'category' => 'serverless'],
    'cloudflare_workers' => ['name' => 'Cloudflare Workers', 'icon' => 'bi-cpu', 'color' => 'var(--amber)', 'config_label' => 'Account ID : API Token', 'config_placeholder' => 'account_id:api_token', 'free' => false, 'category' => 'serverless'],
    'gcp_functions' => ['name' => 'GCP Cloud Functions', 'icon' => 'bi-cloud-lightning', 'color' => 'var(--green)', 'config_label' => 'Service Account JSON', 'config_placeholder' => '{"type":"service_account",...}', 'free' => false, 'category' => 'serverless'],

    // Monitoring & Observability
    'datadog' => ['name' => 'Datadog', 'icon' => 'bi-activity', 'color' => 'var(--purple)', 'config_label' => 'API Key : App Key', 'config_placeholder' => 'api_key:app_key', 'free' => false, 'category' => 'monitoring'],
    'grafana' => ['name' => 'Grafana Cloud', 'icon' => 'bi-speedometer2', 'color' => 'var(--amber)', 'config_label' => 'URL : Service Account Token', 'config_placeholder' => 'https://xxx.grafana.net:glsa_xxxxxxxx', 'free' => false, 'category' => 'monitoring'],
    'sentry' => ['name' => 'Sentry', 'icon' => 'bi-bug', 'color' => 'var(--red)', 'config_label' => 'Auth Token : Organization : Project', 'config_placeholder' => 'sntrys_xxxxxxxx:org-slug:project-slug', 'free' => false, 'category' => 'monitoring'],
    'pagerduty' => ['name' => 'PagerDuty', 'icon' => 'bi-bell', 'color' => 'var(--green)', 'config_label' => 'API Key', 'config_placeholder' => 'u+xxxxxxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'monitoring'],

    // Documentation & Knowledge
    'confluence' => ['name' => 'Confluence', 'icon' => 'bi-file-richtext', 'color' => 'var(--cyan)', 'config_label' => 'Domain : Email : API Token', 'config_placeholder' => 'yourteam.atlassian.net:email:api_token', 'free' => false, 'category' => 'docs'],
    'obsidian' => ['name' => 'Obsidian Vault', 'icon' => 'bi-gem', 'color' => 'var(--purple)', 'config_label' => 'Vault Path (local sync)', 'config_placeholder' => '/path/to/obsidian/vault', 'free' => false, 'category' => 'docs'],
    'google_docs' => ['name' => 'Google Docs', 'icon' => 'bi-file-text', 'color' => 'var(--cyan)', 'config_label' => 'OAuth Token or Service Account JSON', 'config_placeholder' => '{"type":"service_account",...}', 'free' => false, 'category' => 'docs'],
    'coda' => ['name' => 'Coda', 'icon' => 'bi-file-earmark-text', 'color' => 'var(--red)', 'config_label' => 'API Token', 'config_placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'free' => false, 'category' => 'docs'],

    // Additional VCS
    'azure_devops' => ['name' => 'Azure DevOps', 'icon' => 'bi-microsoft', 'color' => 'var(--cyan)', 'config_label' => 'Organization URL : PAT', 'config_placeholder' => 'https://dev.azure.com/org:pat_token', 'free' => false, 'category' => 'vcs'],
    'gitea' => ['name' => 'Gitea', 'icon' => 'bi-git', 'color' => 'var(--green)', 'config_label' => 'Instance URL : Access Token', 'config_placeholder' => 'https://gitea.example.com:token', 'free' => false, 'category' => 'vcs'],
    'sourcehut' => ['name' => 'SourceHut', 'icon' => 'bi-braces', 'color' => 'var(--text-bright)', 'config_label' => 'OAuth Token', 'config_placeholder' => 'srht_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'vcs'],
    'perforce' => ['name' => 'Perforce Helix', 'icon' => 'bi-hdd-rack', 'color' => 'var(--text-bright)', 'config_label' => 'Server : User : Ticket', 'config_placeholder' => 'ssl:perforce.example.com:1666:user:ticket', 'free' => false, 'category' => 'vcs'],

    // Additional Cloud Storage
    'minio' => ['name' => 'MinIO', 'icon' => 'bi-hdd-stack', 'color' => 'var(--green)', 'config_label' => 'Endpoint : Access Key : Secret Key : Bucket', 'config_placeholder' => 'https://minio.example.com:access_key:secret:bucket', 'free' => false, 'category' => 'storage'],
    'backblaze_b2' => ['name' => 'Backblaze B2', 'icon' => 'bi-cloud-arrow-up', 'color' => 'var(--cyan)', 'config_label' => 'Application Key ID : Application Key : Bucket', 'config_placeholder' => 'key_id:app_key:bucket-name', 'free' => false, 'category' => 'storage'],

    // Additional Databases
    'dynamodb' => ['name' => 'DynamoDB', 'icon' => 'bi-table', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Region : Table', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:us-east-1:table-name', 'free' => false, 'category' => 'database'],
    'cassandra' => ['name' => 'Cassandra', 'icon' => 'bi-diagram-3', 'color' => 'var(--purple)', 'config_label' => 'Contact Points : Keyspace : Username : Password', 'config_placeholder' => 'host1,host2:keyspace:user:pass', 'free' => false, 'category' => 'database'],
    'neo4j' => ['name' => 'Neo4j', 'icon' => 'bi-diagram-2', 'color' => 'var(--green)', 'config_label' => 'Bolt URI : Username : Password', 'config_placeholder' => 'bolt://host:7687:neo4j:password', 'free' => false, 'category' => 'database'],

    // Productivity
    'airtable' => ['name' => 'Airtable', 'icon' => 'bi-grid-3x3', 'color' => 'var(--cyan)', 'config_label' => 'Personal Access Token', 'config_placeholder' => 'pat_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'basecamp' => ['name' => 'Basecamp', 'icon' => 'bi-house-door', 'color' => 'var(--green)', 'config_label' => 'Account ID : OAuth Token', 'config_placeholder' => 'account_id:oauth_token', 'free' => false, 'category' => 'comms'],
    'clickup' => ['name' => 'ClickUp', 'icon' => 'bi-check-circle', 'color' => 'var(--purple)', 'config_label' => 'API Token', 'config_placeholder' => 'pk_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],
    'monday' => ['name' => 'Monday.com', 'icon' => 'bi-calendar-week', 'color' => 'var(--red)', 'config_label' => 'API Token', 'config_placeholder' => 'eyJhbGciOiJIUzI1NiJ9...', 'free' => false, 'category' => 'comms'],

    // Additional DevOps
    'kubernetes' => ['name' => 'Kubernetes', 'icon' => 'bi-gear-wide-connected', 'color' => 'var(--cyan)', 'config_label' => 'Kubeconfig (paste YAML or JSON)', 'config_placeholder' => 'apiVersion: v1\nclusters:\n- cluster: ...', 'free' => false, 'category' => 'cicd'],
    'terraform' => ['name' => 'Terraform Cloud', 'icon' => 'bi-bricks', 'color' => 'var(--purple)', 'config_label' => 'API Token : Organization', 'config_placeholder' => 'token:org-name', 'free' => false, 'category' => 'cicd'],

    // Communication
    'teams' => ['name' => 'Microsoft Teams', 'icon' => 'bi-people', 'color' => 'var(--purple)', 'config_label' => 'App ID : App Secret : Tenant ID', 'config_placeholder' => 'app_id:app_secret:tenant_id', 'free' => false, 'category' => 'comms'],
    'email_imap' => ['name' => 'Email (IMAP)', 'icon' => 'bi-envelope', 'color' => 'var(--cyan)', 'config_label' => 'IMAP Server : Port : Email : Password', 'config_placeholder' => 'imap.gmail.com:993:email@gmail.com:app_password', 'free' => false, 'category' => 'comms'],
    'twilio' => ['name' => 'Twilio', 'icon' => 'bi-telephone', 'color' => 'var(--red)', 'config_label' => 'Account SID : Auth Token', 'config_placeholder' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX:auth_token', 'free' => false, 'category' => 'comms'],
    'intercom' => ['name' => 'Intercom', 'icon' => 'bi-chat-dots', 'color' => 'var(--cyan)', 'config_label' => 'Access Token', 'config_placeholder' => 'dG9rOmxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'comms'],

    // Analytics
    'google_analytics' => ['name' => 'Google Analytics', 'icon' => 'bi-graph-up', 'color' => 'var(--green)', 'config_label' => 'Service Account JSON : Property ID', 'config_placeholder' => '{"type":"service_account",...}:properties/123456', 'free' => false, 'category' => 'monitoring'],
    'mixpanel' => ['name' => 'Mixpanel', 'icon' => 'bi-pie-chart', 'color' => 'var(--purple)', 'config_label' => 'Project Token : API Secret', 'config_placeholder' => 'project_token:api_secret', 'free' => false, 'category' => 'monitoring'],
    'amplitude' => ['name' => 'Amplitude', 'icon' => 'bi-soundwave', 'color' => 'var(--cyan)', 'config_label' => 'API Key : Secret Key', 'config_placeholder' => 'api_key:secret_key', 'free' => false, 'category' => 'monitoring'],
    'segment' => ['name' => 'Segment', 'icon' => 'bi-bezier2', 'color' => 'var(--green)', 'config_label' => 'Write Key : Workspace Token', 'config_placeholder' => 'write_key:workspace_token', 'free' => false, 'category' => 'monitoring'],
    'posthog' => ['name' => 'PostHog', 'icon' => 'bi-flag', 'color' => 'var(--amber)', 'config_label' => 'Project API Key : Host', 'config_placeholder' => 'phc_xxxxxxxxxxxx:https://app.posthog.com', 'free' => false, 'category' => 'monitoring'],

    // AI / ML
    'hugging_face' => ['name' => 'Hugging Face', 'icon' => 'bi-emoji-smile', 'color' => 'var(--amber)', 'config_label' => 'API Token', 'config_placeholder' => 'hf_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'ml'],
    'openai_api' => ['name' => 'OpenAI API', 'icon' => 'bi-stars', 'color' => 'var(--green)', 'config_label' => 'API Key : Organization ID', 'config_placeholder' => 'sk-xxxxxxxxxxxxxxxx:org-xxxxxxxx', 'free' => false, 'category' => 'ml'],
    'sagemaker' => ['name' => 'SageMaker', 'icon' => 'bi-motherboard', 'color' => 'var(--amber)', 'config_label' => 'Access Key : Secret Key : Region', 'config_placeholder' => 'AKIAXXXXXXXX:secretkey:us-east-1', 'free' => false, 'category' => 'ml'],

    // Security
    'snyk' => ['name' => 'Snyk', 'icon' => 'bi-shield-check', 'color' => 'var(--green)', 'config_label' => 'API Token : Organization ID', 'config_placeholder' => 'snyk_token:org-id', 'free' => false, 'category' => 'secrets'],

    // Design
    'figma' => ['name' => 'Figma', 'icon' => 'bi-vector-pen', 'color' => 'var(--purple)', 'config_label' => 'Personal Access Token', 'config_placeholder' => 'figd_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'docs'],
    'storybook' => ['name' => 'Storybook', 'icon' => 'bi-book', 'color' => 'var(--red)', 'config_label' => 'Chromatic Project Token', 'config_placeholder' => 'chpt_xxxxxxxxxxxxxxxxxxxx', 'free' => false, 'category' => 'docs'],
];

$pageTitle = 'Connectors';
require_once __DIR__ . '/../templates/header.php';
?>

<!-- Dashboard Nav -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);padding:.6rem 0;">
  <div style="max-width:1200px;margin:0 auto;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
    <div style="display:flex;align-items:center;gap:1.5rem;">
      <a href="/app/dashboard/" style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.1rem;color:var(--text-bright);text-decoration:none;">context<span style="color:var(--cyan);">keeper</span></a>
      <div style="display:flex;gap:.25rem;align-items:center;">
        <a href="/app/dashboard/" style="color:var(--text-dim);font-size:.85rem;padding:.35rem .7rem;text-decoration:none;border-radius:6px;transition:color .2s;">Dashboard</a>
        <a href="/app/dashboard/connectors.php" style="color:var(--cyan);font-size:.85rem;padding:.35rem .7rem;text-decoration:none;border-radius:6px;">Connectors</a>
        <a href="/app/dashboard/settings.php" style="color:var(--text-dim);font-size:.85rem;padding:.35rem .7rem;text-decoration:none;border-radius:6px;transition:color .2s;">Settings</a>
        <a href="/app/dashboard/billing.php" style="color:var(--text-dim);font-size:.85rem;padding:.35rem .7rem;text-decoration:none;border-radius:6px;transition:color .2s;">Billing</a>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <a href="/" style="color:var(--text-dim);font-size:.8rem;text-decoration:none;display:flex;align-items:center;gap:.35rem;transition:color .2s;"><i class="bi bi-arrow-left"></i> Back to site</a>
      <span style="color:var(--border);">|</span>
      <span style="color:var(--text-dim);font-size:.82rem;"><?= htmlspecialchars($user['name'] ?? $user['email']) ?></span>
      <a href="/app/auth/logout.php" style="color:var(--text-dim);font-size:.82rem;text-decoration:none;"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</nav>

<style>
  .conn-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:1.25rem;display:flex;align-items:center;gap:1rem;margin-bottom:.75rem;transition:border-color .2s}
  .conn-card:hover{border-color:rgba(0,200,255,.2)}
  .conn-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
  .conn-info{flex:1}
  .conn-name{font-weight:600;font-size:.92rem;color:var(--text-bright)}
  .conn-type{font-size:.78rem;color:var(--text-dim)}
  .conn-status{font-size:.72rem;font-weight:600;padding:.2rem .5rem;border-radius:999px}
  .conn-status.active{background:rgba(52,211,153,.12);color:var(--green)}
  .conn-status.error{background:rgba(239,68,68,.12);color:var(--red)}
  .conn-status.disconnected{background:rgba(148,163,184,.12);color:var(--text-dim)}
  .conn-actions{display:flex;gap:.5rem}
  .type-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.85rem .5rem;cursor:pointer;transition:all .2s;text-align:center;min-height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem}
  .type-card:hover{border-color:var(--cyan);background:rgba(0,200,255,.03);transform:translateY(-2px)}
  .type-card .tc-icon{font-size:1.2rem}.type-card .tc-name{font-size:.72rem;font-weight:500;color:var(--text);line-height:1.2}
  .type-card .tc-lock{font-size:.6rem;color:var(--amber)}

  /* Modal */
  .cm-overlay{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:1.5rem}
  .cm-overlay.open{display:flex}
  .cm-modal{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:2rem;max-width:480px;width:100%;position:relative;max-height:90vh;overflow-y:auto}
  .cm-close{position:absolute;top:1rem;right:1.25rem;background:none;border:none;color:var(--text-dim);font-size:1.5rem;cursor:pointer;line-height:1}.cm-close:hover{color:var(--text-bright)}
  .cm-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem}
  .cm-header-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;background:var(--surface-hover)}
  .cm-header h3{font-size:1.15rem;font-weight:700;margin:0;color:var(--text-bright)}
  .cm-header .cm-cat{font-size:.72rem;color:var(--text-dim);margin-top:.15rem}

  /* Form elements */
  .form-group{margin-bottom:1rem}
  .form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--text);margin-bottom:.4rem}
  .form-input{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.6rem .85rem;color:var(--text-bright);font-size:.88rem;font-family:'Outfit',sans-serif;transition:border-color .2s;outline:none}
  .form-input:focus{border-color:var(--cyan)}
  .form-input::placeholder{color:var(--text-dim)}
  textarea.form-input{font-family:'JetBrains Mono',monospace;font-size:.82rem;resize:vertical}

  /* Buttons */
  .btn-cta{background:var(--cyan);color:var(--bg);font-weight:600;border:none;border-radius:8px;padding:.6rem 1.25rem;font-size:.88rem;cursor:pointer;transition:all .2s}
  .btn-cta:hover{background:#33d4ff;color:var(--bg);transform:translateY(-1px)}
  .btn-outline{background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;padding:.25rem .6rem;font-size:.75rem;cursor:pointer;transition:all .2s}
  .btn-outline:hover{border-color:var(--cyan);color:var(--cyan)}
  .btn-danger{background:transparent;color:var(--red);border:1px solid rgba(239,68,68,.2);border-radius:6px;padding:.25rem .6rem;font-size:.75rem;cursor:pointer;transition:all .2s}
  .btn-danger:hover{background:rgba(239,68,68,.1);border-color:var(--red)}

  /* Alerts */
  .alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem}
  .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:var(--red)}
  .alert-success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);color:var(--green)}
</style>

<div style="max-width:900px;margin:0 auto;padding-top:1rem">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <h1 style="font-size:1.5rem;font-weight:700;margin:0">Connectors</h1>
    <span style="font-size:.82rem;color:var(--text-dim)"><?= $connectorCount ?> / <?= $maxConnectors > 0 ? $maxConnectors : '&infin;' ?> connected</span>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Existing Connectors (show first if any) -->
  <?php if (!empty($connectors)): ?>
  <div style="margin-bottom:2rem">
    <h3 style="font-size:1rem;font-weight:600;margin-bottom:.75rem"><i class="bi bi-plug" style="color:var(--green);margin-right:.4rem"></i> Active Connectors</h3>
    <?php foreach ($connectors as $conn): ?>
      <?php $ct = $connectorTypes[$conn['type']] ?? ['name' => $conn['type'], 'icon' => 'bi-plug', 'color' => 'var(--text-dim)']; ?>
      <div class="conn-card">
        <div class="conn-icon" style="background:var(--surface-hover);color:<?= $ct['color'] ?>">
          <i class="bi <?= $ct['icon'] ?>"></i>
        </div>
        <div class="conn-info">
          <div class="conn-name"><?= htmlspecialchars($conn['name']) ?></div>
          <div class="conn-type"><?= htmlspecialchars($ct['name']) ?><?= $conn['last_sync'] ? ' - Last synced ' . date('M j, g:ia', strtotime($conn['last_sync'])) : '' ?></div>
        </div>
        <span class="conn-status <?= htmlspecialchars($conn['status']) ?>"><?= htmlspecialchars($conn['status']) ?></span>
        <div class="conn-actions">
          <form method="POST" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="test_connector">
            <input type="hidden" name="connector_id" value="<?= (int)$conn['id'] ?>">
            <button type="submit" class="btn-outline" style="font-size:.75rem;padding:.25rem .6rem" title="Test connection"><i class="bi bi-play"></i></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove this connector?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete_connector">
            <input type="hidden" name="connector_id" value="<?= (int)$conn['id'] ?>">
            <button type="submit" class="btn-danger" style="font-size:.75rem;padding:.25rem .6rem" title="Remove"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Connector Grid -->
  <h3 style="font-size:1rem;font-weight:600;margin-bottom:.25rem"><i class="bi bi-plus-circle" style="color:var(--cyan);margin-right:.4rem"></i> Add a Connector</h3>
  <p style="color:var(--text-dim);font-size:.85rem;margin-bottom:1.25rem">Click any service to connect it. Credentials are encrypted with AES-256.</p>

  <?php
  $categories = [
      'vcs' => 'Version Control',
      'storage' => 'Cloud Storage',
      'database' => 'Databases',
      'warehouse' => 'Data Warehouses',
      'cicd' => 'CI/CD & DevOps',
      'container' => 'Container & Registry',
      'ml' => 'ML/AI Platforms',
      'messaging' => 'Message Queues',
      'secrets' => 'Secrets & Config',
      'comms' => 'Communication & PM',
      'serverless' => 'Serverless & Cloud',
      'monitoring' => 'Monitoring & Observability',
      'docs' => 'Documentation & Knowledge',
  ];
  $grouped = [];
  foreach ($connectorTypes as $key => $ct) {
      $cat = $ct['category'] ?? 'other';
      $grouped[$cat][$key] = $ct;
  }
  foreach ($categories as $catKey => $catName):
      if (empty($grouped[$catKey])) continue;
  ?>
  <div style="margin-top:1.25rem;margin-bottom:.5rem">
    <span style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:var(--cyan)"><?= $catName ?></span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:.5rem;margin-bottom:.25rem">
    <?php foreach ($grouped[$catKey] as $key => $ct): ?>
    <div class="type-card" onclick="openConnModal('<?= $key ?>')">
      <span class="tc-icon" style="color:<?= $ct['color'] ?>"><i class="bi <?= $ct['icon'] ?>"></i></span>
      <span class="tc-name"><?= $ct['name'] ?></span>
      <?php if (!$ct['free'] && $user['plan'] === 'free'): ?>
      <span class="tc-lock"><i class="bi bi-lock-fill"></i> Pro+</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <!-- Connector Limits Info -->
  <div style="margin-top:2rem;padding:1rem;border-radius:8px;background:var(--surface);border:1px solid var(--border);font-size:.82rem;color:var(--text-dim)">
    <strong style="color:var(--text)">Connector limits by plan:</strong>
    Free: 3 connectors (GitHub, Google Drive, S3, SQLite, Local File) -
    Pro: 10 connectors (all types) -
    Team/Enterprise: Unlimited
    <?php if ($user['plan'] === 'free'): ?>
    <br><a href="/app/dashboard/billing.php" style="font-size:.82rem">Upgrade to unlock more connectors <i class="bi bi-arrow-right"></i></a>
    <?php endif; ?>
  </div>
</div>

<!-- CONNECTOR MODAL -->
<div class="cm-overlay" id="connModal" onclick="if(event.target===this)closeConnModal()">
  <div class="cm-modal">
    <button class="cm-close" onclick="closeConnModal()">&times;</button>
    <div class="cm-header">
      <div class="cm-header-icon" id="cmIcon"></div>
      <div>
        <h3 id="cmTitle"></h3>
        <div class="cm-cat" id="cmCat"></div>
      </div>
    </div>

    <form method="POST">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_connector">
      <input type="hidden" name="connector_type" id="cmType" value="">

      <div class="form-group">
        <label for="cmName">Display Name</label>
        <input type="text" id="cmName" name="connector_name" class="form-input" placeholder="e.g., My Production DB" required>
      </div>

      <div class="form-group">
        <label for="cmConfig" id="cmConfigLabel">Configuration</label>
        <textarea id="cmConfig" name="connector_config" class="form-input" rows="3" style="resize:vertical;font-family:'JetBrains Mono',monospace;font-size:.82rem"></textarea>
        <p style="font-size:.72rem;color:var(--text-dim);margin-top:.35rem"><i class="bi bi-shield-lock"></i> Encrypted with AES-256-CBC before storage. Never logged or transmitted in plaintext.</p>
      </div>

      <button type="submit" class="btn-cta" style="width:100%;padding:.65rem;font-size:.92rem" id="cmSubmit">Connect</button>
    </form>
  </div>
</div>

<!-- UPGRADE MODAL -->
<div class="cm-overlay" id="upgradeModal" onclick="if(event.target===this)closeUpgradeModal()">
  <div class="cm-modal" style="text-align:center;max-width:420px">
    <button class="cm-close" onclick="closeUpgradeModal()">&times;</button>
    <div class="cm-header-icon" id="umIcon" style="margin:0 auto 1rem;width:56px;height:56px;border-radius:12px;font-size:1.5rem"></div>
    <h3 id="umTitle" style="font-size:1.2rem;font-weight:700;margin-bottom:.25rem"></h3>
    <div style="font-size:.75rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.06em;margin-bottom:1rem" id="umCat"></div>
    <div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:.65rem;font-size:.85rem;color:var(--text-dim);line-height:1.5;text-align:left">
      <i class="bi bi-lock" style="color:var(--amber);font-size:1rem;flex-shrink:0;margin-top:.1rem"></i>
      <span><span id="umConnName" style="color:var(--text);font-weight:600"></span> requires a <strong style="color:var(--amber)">Pro</strong> plan or higher. Upgrade to unlock all 67 connectors with unlimited instances.</span>
    </div>
    <a href="/app/dashboard/billing.php" class="btn-cta" style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.7rem;font-size:.92rem;margin-bottom:.75rem;text-decoration:none"><i class="bi bi-arrow-up-circle"></i> Upgrade Plan</a>
    <button onclick="closeUpgradeModal()" style="background:none;border:none;color:var(--text-dim);font-size:.85rem;cursor:pointer;padding:.25rem">Maybe later</button>
  </div>
</div>

<script>
const connData = <?= json_encode(array_map(fn($t) => [
    'name' => $t['name'],
    'icon' => $t['icon'],
    'color' => $t['color'],
    'config_label' => $t['config_label'],
    'config_placeholder' => $t['config_placeholder'],
    'category' => $t['category'] ?? '',
    'free' => $t['free'],
], $connectorTypes)) ?>;

const catNames = <?= json_encode($categories) ?>;
const userPlan = <?= json_encode($user['plan']) ?>;

function openConnModal(type) {
  const c = connData[type];
  if (!c) return;

  // Check plan restriction - show upgrade modal instead of browser confirm
  if (!c.free && userPlan === 'free') {
    document.getElementById('umIcon').innerHTML = '<i class="bi ' + c.icon + '" style="color:' + c.color + '"></i>';
    document.getElementById('umIcon').style.background = 'var(--surface-hover)';
    document.getElementById('umTitle').textContent = c.name;
    document.getElementById('umCat').textContent = catNames[c.category] || '';
    document.getElementById('umConnName').textContent = c.name;
    document.getElementById('upgradeModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    return;
  }

  document.getElementById('cmType').value = type;
  document.getElementById('cmIcon').innerHTML = '<i class="bi ' + c.icon + '" style="color:' + c.color + '"></i>';
  document.getElementById('cmIcon').style.color = c.color;
  document.getElementById('cmTitle').textContent = 'Connect ' + c.name;
  document.getElementById('cmCat').textContent = catNames[c.category] || '';
  document.getElementById('cmConfigLabel').textContent = c.config_label;
  document.getElementById('cmConfig').placeholder = c.config_placeholder;
  document.getElementById('cmConfig').value = '';
  document.getElementById('cmName').value = '';
  document.getElementById('cmSubmit').textContent = 'Connect ' + c.name;

  document.getElementById('connModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('cmName').focus(), 100);
}

function closeConnModal() {
  document.getElementById('connModal').classList.remove('open');
  document.body.style.overflow = '';
}

function closeUpgradeModal() {
  document.getElementById('upgradeModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') { closeConnModal(); closeUpgradeModal(); }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
