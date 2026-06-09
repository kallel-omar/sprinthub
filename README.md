# SprintHub

SprintHub is a Project Management SaaS built with Symfony 7, inspired by tools like Jira and Trello.

The goal of this project is to strengthen my full-stack development skills, learn advanced Symfony concepts, and build a real-world collaborative platform.

## Features

### Authentication & Users

* User registration
* Secure authentication
* User profiles
* Avatar upload

### Workspaces

* Create and manage workspaces
* Workspace invitations
* Workspace member management
* Workspace roles:

  * Owner
  * Admin
  * Member

### Projects

* Create projects inside workspaces
* Project member management
* Project-specific collaboration

### Tasks

* Create, edit and delete tasks
* Task priorities
* Due dates
* Task assignment
* Assignment restricted to project members
* Task labels
* Task status management

### Collaboration

* Task comments
* File attachments
* Task checklists
* Activity logs

### Productivity

* Notifications
* Calendar view
* Analytics dashboard
* Task filtering

## Tech Stack

### Backend

* Symfony 7
* PHP 8
* Doctrine ORM
* MySQL

### Frontend

* Twig
* Bootstrap 5
* JavaScript

### Tools

* Composer
* Git
* GitHub
* Docker

## Screenshots

### Dashboard

(Add screenshot)

### Workspace Management

(Add screenshot)

### Project Board

(Add screenshot)

### Task Details

(Add screenshot)

### Calendar

(Add screenshot)

## Installation

Clone the repository:

```bash
git clone https://github.com/kallel-omar/sprinthub.git
cd sprinthub
```

Install dependencies:

```bash
composer install
```

Configure environment variables:

```bash
cp .env .env.local
```

Update your database configuration inside `.env.local`.

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

Start the Symfony server:

```bash
symfony server:start
```

## Future Improvements

* My Tasks dashboard
* Email notifications
* REST API
* Automated tests
* Real-time notifications
* Project archiving

## Author

**Omar Kallel**

GitHub: https://github.com/kallel-omar
