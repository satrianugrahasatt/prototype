variable "project_id" {
  description = "The Google Cloud project ID"
  default = "feisty-reality-461020-d5"
}

variable "db_password" {
  description = "The password for the database user"
  default = ""
}

provider "google" {
  project = var.project_id
  region  = "us-central1"
}

resource "google_sql_database_instance" "instance" {
  name             = "employeemanagement-dev"
  database_version = "MYSQL_8_0"
  region           = "us-central1"

  settings {
    tier = "db-f1-micro"
    backup_configuration {
      enabled = true
    }
  }

  deletion_protection = false
}

resource "google_sql_database" "database" {
  name     = "fp_pso"
  instance = google_sql_database_instance.instance.name
}

resource "google_sql_user" "user" {
  name     = "root"
  instance = google_sql_database_instance.instance.name
  password = var.db_password
}

output "connection_name" {
  value = google_sql_database_instance.instance.connection_name
}
