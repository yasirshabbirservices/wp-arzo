# WordPress Maintenance Tool

![Version](https://img.shields.io/badge/version-5.0-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)

A comprehensive WordPress maintenance and system administration tool designed for developers, system administrators, and site owners who need advanced control over their WordPress installations.

## 🚀 Features

### 🔐 Security & Access Control
- **Secure Access Key Protection**: Configurable access key prevents unauthorized usage
- **Emergency Login Options**: Multiple methods to regain admin access
- **Session Management**: Proper WordPress authentication handling

### 📊 System Information
- **Site Overview**: Complete WordPress installation details
- **Server Information**: PHP version, memory limits, and server configuration
- **Database Status**: Connection details and basic statistics
- **Performance Metrics**: Memory usage and execution time monitoring

### 👥 User Management
- **User Listing**: View all WordPress users with roles and details
- **Quick Login**: Login as any existing user instantly
- **Temporary Admin Creation**: Generate temporary administrator accounts
- **Direct Admin Access**: Create secure bypass links for emergency access
- **User Role Management**: View and understand user permissions

### 🗄️ Database Operations
- **Database Information**: View database name, size, and table count
- **Connection Testing**: Verify database connectivity
- **Table Overview**: List all database tables with sizes
- **Query Execution**: Basic database query capabilities

### 📁 Advanced File Manager
- **File Browser**: Navigate through WordPress directory structure
- **File Viewer**: Preview files with syntax highlighting
- **File Editor**: Edit text files directly in the browser
- **File Upload**: Upload files to any directory
- **File Download**: Download files from the server
- **File Operations**: Delete, rename, and manage files
- **Binary File Support**: Preview images and handle binary files
- **Security Checks**: Validate file types and permissions

### 🔌 Plugin Management
- **Plugin Listing**: View all installed plugins
- **Plugin Control**: Activate/deactivate plugins
- **Plugin Information**: Version details and status
- **Bulk Operations**: Manage multiple plugins efficiently

### 🎨 Theme Management
- **Theme Overview**: List all installed themes
- **Active Theme Detection**: Identify currently active theme
- **Theme Information**: Version and status details
- **Theme Switching**: Quick theme activation

### 🎯 Emergency Features
- **Locked Out Recovery**: Multiple methods to regain access
- **Maintenance Mode**: Safe system maintenance capabilities
- **Backup Verification**: Verify backup integrity and restoration
- **System Diagnostics**: Troubleshoot common WordPress issues

## 📋 Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **Server**: Apache/Nginx with mod_rewrite
- **Permissions**: Write access to WordPress directory
- **Memory**: Minimum 128MB PHP memory limit

## 🛠️ Installation

1. **Download the Tool**
   ```bash
   wget https://github.com/yasirshabbirservices/maintenance-tool/archive/refs/heads/main.zip
   unzip main.zip
   ```

2. **Upload to WordPress Root**
   - Upload `maintenance-tool.php` to your WordPress root directory
   - Ensure it's in the same directory as `wp-config.php`

3. **Configure Security Key**
   ```php
   // Edit line 11 in maintenance-tool.php
   define('ACCESS_KEY', 'your-unique-secure-key-here');
   ```

4. **Set Permissions**
   ```bash
   chmod 644 maintenance-tool.php
   ```

5. **Access the Tool**
   ```
   https://yoursite.com/maintenance-tool.php?key=your-unique-secure-key-here
   ```

## 🔧 Configuration

### Security Key Setup
The tool uses a secure access key to prevent unauthorized access:

```php
define('ACCESS_KEY', 'YS_maint_7x9K2pQ8vL4nB6wE3rT5uA1cF8dG');
```

**Important**: Change this to a unique, complex string before deployment.

### WordPress Integration
The tool automatically detects WordPress installation in these locations:
- Same directory as the tool
- Parent directory
- Two levels up

### File Permissions
Ensure proper file permissions for full functionality:
- Read access: View files and directories
- Write access: Edit, upload, and delete files
- Execute access: Navigate directories

## 🚦 Usage

### Basic Access
1. Navigate to `https://yoursite.com/maintenance-tool.php?key=YOUR_ACCESS_KEY`
2. Use the navigation menu to access different features
3. Each section provides specific functionality for WordPress management

### Emergency Access
If locked out of WordPress admin:
1. Go to the "Quick Login" tab
2. Choose from three recovery methods:
   - Login as existing user
   - Create temporary admin
   - Generate direct admin access link

### File Management
1. Navigate to the "Files" tab
2. Browse directories using the file manager
3. Click file icons to view, edit, or download
4. Use upload form to add new files

### System Monitoring
1. Check "Site Info" for system overview
2. Monitor "Database" for connection status
3. Review "Users" for account management

## ⚠️ Security Considerations

### Access Control
- **Always use a strong, unique access key**
- **Remove the tool after maintenance is complete**
- **Never leave the tool accessible on production sites**
- **Use HTTPS for all access to prevent key interception**

### File Permissions
- **Limit write permissions to necessary directories only**
- **Regularly audit file changes made through the tool**
- **Monitor access logs for unauthorized usage**

### Emergency Procedures
- **Document your access key in a secure location**
- **Test emergency access methods before needed**
- **Have backup restoration procedures ready**

## 🐛 Troubleshooting

### Common Issues

**"WordPress not found" Error**
- Ensure the tool is in the correct directory
- Check WordPress installation paths
- Verify file permissions

**"File not found" Error**
- Verify the access key is correct
- Check URL parameters
- Ensure the tool file exists

**File Upload Failures**
- Check PHP upload limits
- Verify directory permissions
- Review server error logs

**Database Connection Issues**
- Verify wp-config.php settings
- Check database server status
- Review MySQL/MariaDB logs

### Debug Mode
Enable WordPress debug mode for detailed error information:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Contributing

We welcome contributions to improve the WordPress Maintenance Tool!

### How to Contribute
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow WordPress coding standards
- Add comments for complex functionality
- Test all features thoroughly
- Update documentation for new features

### Reporting Issues
- Use the GitHub issue tracker
- Provide detailed reproduction steps
- Include system information
- Attach relevant error logs

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### MIT License Summary

**Permissions:**
- ✅ Commercial use
- ✅ Modification
- ✅ Distribution
- ✅ Private use

**Conditions:**
- 📋 License and copyright notice

**Limitations:**
- ❌ Liability
- ❌ Warranty

## 👨‍💻 Author

**Yasir Shabbir**
- Website: [yasirshabbir.com](https://yasirshabbir.com)
- Email: [contact@yasirshabbir.com](mailto:contact@yasirshabbir.com)
- GitHub: [@yasirshabbir](https://github.com/yasirshabbir)

## 🙏 Acknowledgments

- WordPress community for excellent documentation
- PHP community for robust language features
- Open source contributors for inspiration
- Security researchers for best practices

## 📚 Additional Resources

- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [WordPress Security Guide](https://wordpress.org/support/article/hardening-wordpress/)
- [GPL License Guide](https://www.gnu.org/licenses/gpl-3.0.en.html)

## 🔄 Changelog

### Version 5.0 (Current)
- Enhanced file management system with improved performance
- Advanced user authentication and management capabilities
- Optimized database operations and monitoring
- Comprehensive plugin and theme management
- Multiple emergency access recovery methods
- Modern responsive UI with better UX
- Enhanced security hardening and access controls
- Improved system diagnostics and troubleshooting
- Better error handling and user feedback
- Streamlined codebase with performance optimizations

---

**⚠️ Important Notice**: This tool provides powerful administrative capabilities. Use responsibly and always maintain proper backups before making system changes.

**🔒 Security Reminder**: Remove this tool from production servers after maintenance is complete to prevent unauthorized access.
