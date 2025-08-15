# Blog Website - Complete PHP/MySQL Blog System

A complete, user-friendly, and robust blog website built with XAMPP (Apache, MySQL, PHP) for the backend and HTML, CSS, and JavaScript for the frontend.

## Features

### Core Features
- User registration and authentication
- Role-based access control (User/Admin)
- Blog post creation with rich text editor
- Image upload for featured images
- Category and tag system
- Nested comments with moderation
- Search functionality
- Responsive design

### Unique Features
- Rich text editor (TinyMCE) for post creation
- Dynamic tagging with auto-suggestions
- Nested comments/replies
- Related posts section
- Comment moderation system
- Featured posts
- AJAX comment submission
- Social media sharing buttons

### Admin Panel
- Dashboard with statistics
- Post management (CRUD operations)
- User management
- Comment moderation
- Category and tag management

## Installation Instructions

### Prerequisites
- XAMPP installed and running
- Apache and MySQL services started
- PHP 7.4 or higher

### Setup Steps

1. **Copy Project Files**
   - Copy the `blog_website` folder to your XAMPP `htdocs` directory
   - Typical path: `C:\xampp\htdocs\blog_website` (Windows) or `/Applications/XAMPP/htdocs/blog_website` (macOS)

2. **Database Setup**
   - Open phpMyAdmin: `http://localhost/phpmyadmin/`
   - Import the `blog_db.sql` file to create the database and tables
   - Or manually create the database using the SQL script

3. **Configure Database Connection**
   - Open `includes/db_connection.php`
   - Update database credentials if needed (default: localhost, root, no password)

4. **Insert Sample Data (Optional)**
   - Run `http://localhost/blog_website/sample_data.php` in your browser
   - This creates sample categories, tags, and test users

5. **Access the Website**
   - Homepage: `http://localhost/blog_website/`
   - Admin Panel: `http://localhost/blog_website/admin/`

## Default Login Credentials

### Admin Account
- Email: `admin@example.com`
- Password: `admin123`

### Test User Account
- Email: `user@example.com`
- Password: `user123`

## File Structure

```
blog_website/
├── index.php                 # Homepage
├── register.php              # User Registration
├── login.php                 # User Login
├── logout.php                # User Logout
├── dashboard.php             # User Dashboard
├── create_post.php           # Post Creation
├── post.php                  # Single Post View
├── category.php              # Category Posts
├── tag.php                   # Tag Posts
├── search.php                # Search Results
├── admin/                    # Admin Panel
│   └── index.php             # Admin Dashboard
├── includes/
│   ├── db_connection.php     # Database Connection
│   ├── functions.php         # Utility Functions
│   ├── header.php            # Header Template
│   └── footer.php            # Footer Template
├── assets/
│   ├── css/
│   │   └── style.css         # Main Stylesheet
│   ├── js/
│   │   └── script.js         # Main JavaScript
│   └── images/               # Static Images
├── uploads/                  # User Uploaded Images
├── blog_db.sql              # Database Schema
└── sample_data.php          # Sample Data Script
```

## Database Schema

### Tables
- `users` - User accounts and authentication
- `posts` - Blog posts with content and metadata
- `categories` - Post categories
- `comments` - User comments with nesting support
- `tags` - Post tags
- `post_tags` - Many-to-many relationship between posts and tags

## Key Features Explained

### Rich Text Editor
- Integrated TinyMCE for WYSIWYG editing
- Supports formatting, links, and media insertion
- Live preview functionality

### Comment System
- Nested comments with unlimited depth
- Comment moderation (pending/approved/rejected)
- AJAX submission for smooth user experience
- Guest and registered user comments

### Search Functionality
- Full-text search across post titles and content
- Search result highlighting
- Popular categories and tags display

### Admin Panel
- Comprehensive dashboard with statistics
- Complete CRUD operations for all content
- User role management
- Comment moderation interface

### Security Features
- Password hashing with PHP's password_hash()
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Role-based access control
- Session management

## Customization

### Styling
- Modify `assets/css/style.css` for visual customization
- Responsive design with mobile-first approach
- CSS Grid and Flexbox for layouts

### Functionality
- Add new features by extending existing PHP files
- JavaScript enhancements in `assets/js/script.js`
- Database schema can be extended as needed

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check XAMPP MySQL service is running
   - Verify database credentials in `includes/db_connection.php`
   - Ensure `blog_db` database exists

2. **File Upload Issues**
   - Check `uploads/` directory permissions
   - Verify PHP upload settings in `php.ini`
   - Ensure sufficient disk space

3. **Rich Text Editor Not Loading**
   - Check internet connection (TinyMCE loads from CDN)
   - Verify JavaScript is enabled in browser

4. **Admin Panel Access Denied**
   - Ensure user has admin role in database
   - Check session is properly started

## Browser Compatibility
- Chrome (recommended)
- Firefox
- Safari
- Edge
- Mobile browsers (responsive design)

## Performance Considerations
- Images are automatically resized on upload
- Pagination implemented for large datasets
- Efficient database queries with proper indexing
- CSS and JavaScript minification recommended for production

## Security Notes
- Change default admin credentials immediately
- Use HTTPS in production environment
- Regular database backups recommended
- Keep PHP and MySQL updated

## Support
For issues or questions, refer to the code comments and documentation within the PHP files.

