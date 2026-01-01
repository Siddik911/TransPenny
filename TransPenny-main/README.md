# TransPenny - Team Web Project

A professional, containerized web application built with vanilla PHP, JavaScript, MySQL, and Docker. This project demonstrates a complete team development workflow with zero local dependencies beyond Docker.

## 🎯 Project Overview

**Tech Stack:**
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 8.2 (Apache)
- **Database:** MySQL 8.0
- **Communication:** AJAX (XMLHttpRequest)
- **Environment:** Docker & Docker Compose

**Key Features:**
- ✅ Fully containerized development environment
- ✅ One-command setup and deployment
- ✅ Environment variable configuration
- ✅ RESTful API architecture
- ✅ Secure database connections
- ✅ AJAX-based async communication
- ✅ Persistent data storage with Docker volumes

## 📁 Project Structure

```
TransPenny/
├── docker-compose.yml          # Container orchestration
├── Dockerfile                  # PHP + Apache image configuration
├── .env.example                # Environment variables template
├── .gitignore                  # Git ignore rules
├── README.md                   # Project documentation
│
├── database/
│   └── init.sql               # Database initialization script
│
└── src/                       # Application source code
    ├── index.html             # Main application page
    ├── .htaccess              # Apache configuration
    │
    ├── config/
    │   └── database.php       # Database connection class
    │
    ├── api/
    │   ├── add_user.php       # API: Add new user
    │   ├── get_users.php      # API: Get all users
    │   └── test_connection.php # API: Test database connection
    │
    └── assets/
        ├── css/
        │   └── styles.css     # Application styles
        └── js/
            └── app.js         # AJAX logic & DOM manipulation
```

## 🚀 Prerequisites

Install these tools on your machine:

1. **Docker Desktop** (includes Docker Compose)
   - Windows/Mac: [Download Docker Desktop](https://www.docker.com/products/docker-desktop)
   - Linux: Install Docker Engine and Docker Compose separately

2. **Git** (for cloning the repository)
   - [Download Git](https://git-scm.com/downloads)

Verify installations:
```bash
docker --version
docker compose version
git --version
```

## 📥 Setup Instructions

### Step 1: Clone the Repository

```bash
git clone <repository-url>
cd TransPenny
```

### Step 2: Configure Environment

Copy the environment template and customize if needed:

```bash
# Windows (PowerShell)
Copy-Item .env.example .env

# Linux/Mac
cp .env.example .env
```

**Default configuration (.env):**
```env
DB_HOST=db
DB_NAME=transpenny_db
DB_USER=transpenny_user
DB_PASSWORD=transpenny_pass_2025
DB_ROOT_PASSWORD=root_pass_2025
```

> ⚠️ **Security Note:** Change passwords in production environments!

### Step 3: Start the Application

Build and start all containers:

```bash
docker compose up --build
```

**What happens:**
1. Docker builds the PHP + Apache image with required extensions
2. MySQL container starts with persistent volume
3. Database is initialized with `init.sql`
4. Application becomes available at `http://localhost:8080`

### Step 4: Verify Installation

Open your browser and navigate to:
- **Application:** http://localhost:8080
- **Connection Test:** http://localhost:8080/api/test_connection.php

You should see the TransPenny interface with the ability to add and view users.

## 🛠️ Development Workflow

### Running the Application

**Start containers (foreground):**
```bash
docker compose up
```

**Start containers (background/detached):**
```bash
docker compose up -d
```

**Stop containers:**
```bash
docker compose down
```

**Stop and remove volumes (⚠️ deletes database data):**
```bash
docker compose down -v
```

### Viewing Logs

```bash
# All services
docker compose logs

# Specific service
docker compose logs web
docker compose logs db

# Follow logs (live)
docker compose logs -f
```

### Accessing Containers

```bash
# Access web container shell
docker exec -it transpenny_web bash

# Access MySQL CLI
docker exec -it transpenny_db mysql -u transpenny_user -p
```

### Database Management

**Connect to MySQL:**
```bash
docker exec -it transpenny_db mysql -u root -p
# Enter password: root_pass_2025
```

**Backup database:**
```bash
docker exec transpenny_db mysqldump -u root -p transpenny_db > backup.sql
```

**Restore database:**
```bash
docker exec -i transpenny_db mysql -u root -p transpenny_db < backup.sql
```

## 📚 API Documentation

### Endpoints

#### 1. Add User
- **URL:** `/api/add_user.php`
- **Method:** `POST`
- **Content-Type:** `application/json`
- **Body:**
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com"
  }
  ```
- **Success Response (201):**
  ```json
  {
    "success": true,
    "message": "User added successfully",
    "data": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  }
  ```

#### 2. Get Users
- **URL:** `/api/get_users.php`
- **Method:** `GET`
- **Success Response (200):**
  ```json
  {
    "success": true,
    "message": "Users retrieved successfully",
    "data": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2025-12-26 10:30:00"
      }
    ],
    "count": 1
  }
  ```

#### 3. Test Connection
- **URL:** `/api/test_connection.php`
- **Method:** `GET`
- **Purpose:** Verify database connectivity and environment configuration

## 🔒 Security Best Practices

1. **Environment Variables:** Never commit `.env` file to Git
2. **Password Strength:** Use strong passwords in production
3. **Input Validation:** All API endpoints validate and sanitize input
4. **SQL Injection Prevention:** Using prepared statements with PDO
5. **XSS Protection:** HTML escaping in JavaScript
6. **Error Logging:** Errors logged to file, not exposed to users

## 🧪 Testing

### Manual Testing

1. **Add a user via form**
   - Fill in name and email
   - Submit form
   - Verify success message

2. **Load users list**
   - Click "Load Users" button
   - Verify users appear correctly

3. **Test API directly**
   ```bash
   # Add user
   curl -X POST http://localhost:8080/api/add_user.php \
     -H "Content-Type: application/json" \
     -d '{"name":"Test User","email":"test@example.com"}'

   # Get users
   curl http://localhost:8080/api/get_users.php
   ```

## 🐛 Troubleshooting

### Port Already in Use
```bash
# Windows: Find and kill process
netstat -ano | findstr :8080
taskkill /PID <process_id> /F

# Linux/Mac
lsof -ti:8080 | xargs kill -9
```

### Database Connection Failed
1. Verify containers are running: `docker compose ps`
2. Check environment variables: `docker compose config`
3. View logs: `docker compose logs db`
4. Restart containers: `docker compose restart`

### Permission Denied Errors
```bash
# Linux: Fix file permissions
sudo chown -R $USER:$USER .
```

## 🚢 Deployment

### Production Deployment

1. **Update environment variables** with secure passwords
2. **Remove development settings** (APP_DEBUG=false)
3. **Use Docker secrets** or cloud provider secrets management
4. **Enable HTTPS** with reverse proxy (nginx/Traefik)
5. **Implement backup strategy** for database volumes

### Cloud Deployment Options

- **AWS:** ECS/Fargate + RDS
- **Azure:** Container Instances + Azure Database for MySQL
- **Google Cloud:** Cloud Run + Cloud SQL
- **DigitalOcean:** App Platform + Managed Database

## 👥 Team Collaboration

### For New Team Members

1. Clone the repository
2. Copy `.env.example` to `.env`
3. Run `docker compose up`
4. Start coding!

No need to install PHP, Apache, MySQL, or any local dependencies.

### Git Workflow

```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes and commit
git add .
git commit -m "Add new feature"

# Push to remote
git push origin feature/new-feature

# Create pull request on GitHub
```

## 📖 Learning Resources

- [Docker Documentation](https://docs.docker.com/)
- [PHP Documentation](https://www.php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [MDN Web Docs - AJAX](https://developer.mozilla.org/en-US/docs/Web/Guide/AJAX)

## 📝 License

This project is open source and available for educational purposes.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

---

**Happy Coding! 🚀**

For issues or questions, please open a GitHub issue or contact the development team.
