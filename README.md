# WordPress Maintenance Tool

![Version](https://img.shields.io/badge/version-5.0-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)

A comprehensive WordPress maintenance and system administration tool designed for developers, system administrators, and site owners who need advanced control over their WordPress installations.


[![Watch the video](https://github.com/yasirshabbirservices/wp-arzo/blob/main/screenshot.png)](https://komododecks.com/recordings/NuYsHyzxirWxfosHv0Hk?onlyRecording=1)

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

### 🚧 Maintenance Modes
- **Multiple Mode Options**: Choose from maintenance, coming soon, or payment request modes
- **Custom Messaging**: Personalized titles and messages for each mode
- **Social Contact Integration**: Display developer contact information (email, phone, WhatsApp, Skype)
- **SEO-Friendly**: Proper HTTP status codes and noindex meta tags
- **Bypass Access**: Administrators and bypass URL users can access the site normally
- **Custom CSS Support**: Add custom styling to maintenance pages
- **Real-time Preview**: Live preview and management of active modes

#### Available Modes:
- **🔧 Maintenance Mode**: Display maintenance message with 503 status code (temporary unavailable)
- **🚀 Coming Soon Mode**: Show coming soon page with 200 status code and email collection
- **💰 Payment Request Mode**: Display payment request message with 402 status code for unpaid projects

### 🔧 Debug Management
- **Visual Debug Controls**: Interactive interface to enable/disable WordPress debug settings
- **Real-time Debug Status**: Live monitoring of WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, and SAVEQUERIES
- **Debug Log Viewer**: Built-in viewer for recent debug log entries with real-time monitoring
- **wp-config.php Auto-editing**: Safe automatic modification of WordPress configuration
- **Debug Information Panel**: Comprehensive debug environment details and file status
- **Debug Settings Guide**: Built-in documentation and recommended configurations

### 🎨 User Interface & Experience
- **Font Awesome Icons**: Professional icon system with 6.4.0 CDN integration
- **Branding Color Variables**: Consistent design system with CSS custom properties
- **Interactive Lightbox Modals**: Enhanced user experience for complex instructions
- **Responsive Design**: Mobile-friendly interface with modern styling
- **Visual Feedback**: Clear status indicators and error messages with contextual icons

### 🎯 Emergency Features
- **Locked Out Recovery**: Multiple methods to regain access
- **Maintenance Mode**: Safe system maintenance capabilities with multiple mode options
- **Backup Verification**: Verify backup integrity and restoration
- **System Diagnostics**: Troubleshoot common WordPress issues
- **Frontend Installation Guide**: Interactive lightbox with detailed setup instructions
- **Visual Error Indicators**: Clear warnings with Font Awesome icons for missing components

## 📋 Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **Server**: Apache/Nginx with mod_rewrite
- **Permissions**: Write access to WordPress directory
- **Memory**: Minimum 128MB PHP memory limit

## 🛠️ Installation

1. **Download the Tool**
   ```bash
   wget https://github.com/yasirshabbirservices/wp-arzo/archive/refs/heads/main.zip
   unzip main.zip
   ```

2. **Upload to WordPress Root**
   - Upload `wp-arzo.php` to your WordPress root directory
   - Ensure it's in the same directory as `wp-config.php`

3. **Configure Security Key**
   ```php
   // Edit line 11 in wp-arzo.php
   define('ACCESS_KEY', 'your-unique-secure-key-here');
   ```

4. **Set Permissions**
   ```bash
   chmod 644 wp-arzo.php
   ```

5. **Access the Tool**
   ```
   https://yoursite.com/wp-arzo.php?key=your-unique-secure-key-here
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

1. Navigate to `https://yoursite.com/wp-arzo.php?key=YOUR_ACCESS_KEY`
2. Use the navigation menu to access different features
3. Each section provides specific functionality for WordPress management

### Maintenance Modes Usage

#### Setting Up Maintenance Modes
1. **Install Frontend Handler**: Download and install the `maintenance-tool-frontend.php` file to `wp-content/mu-plugins/`
2. **Configure Social Contacts**: Set up developer contact information (email, phone, WhatsApp, Skype)
3. **Choose Mode**: Select from maintenance, coming soon, or payment request modes
4. **Customize Content**: Add custom titles, messages, and CSS styling
5. **Activate Mode**: Click the respective activation button

#### Mode Features
- **🔧 Maintenance Mode**: Perfect for scheduled maintenance, updates, or repairs
  - Returns HTTP 503 status (Service Unavailable)
  - Includes noindex meta tag to prevent search engine indexing
  - Shows maintenance message with estimated completion time

- **🚀 Coming Soon Mode**: Ideal for new websites under development
  - Returns HTTP 200 status (OK)
  - Includes noindex meta tag and email collection form
  - Professional coming soon page with social contact options

- **💰 Payment Request Mode**: For projects with pending payments
  - Returns HTTP 402 status (Payment Required)
  - Includes noindex meta tag and contact information
  - Clear payment request message with developer contact details

#### Bypass Access
- **Administrator Access**: Logged-in administrators can always access the site
- **Bypass URL**: Use `?maintenance_bypass=YOUR_ACCESS_KEY` to view the normal site
- **Preview Mode**: Real-time preview of maintenance pages before activation

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

### Debug Management
1. Navigate to the "Debug" tab
2. View current debug settings status with color-coded indicators
3. Enable/disable debug settings using the interactive form
4. Monitor debug log file size and recent entries
5. Use the built-in guide for recommended configurations

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
Use the built-in Debug Management feature to easily configure WordPress debug settings:
1. Go to the "Debug" tab
2. Use the visual interface to enable/disable debug options
3. Monitor debug logs in real-time
4. The tool automatically updates wp-config.php with proper settings

Alternatively, manually enable debug mode:
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
- GitHub: [@yasirshabbirservices](https://github.com/yasirshabbirservices/wp-arzo)

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
- **NEW: Debug Management System** - Visual interface for WordPress debug settings
- **NEW: Real-time Debug Monitoring** - Live status of all debug constants
- **NEW: Debug Log Viewer** - Built-in viewer for debug.log with recent entries
- **NEW: wp-config.php Auto-editing** - Safe automatic configuration updates
- **NEW: Enhanced Frontend Installation Guide** - Interactive lightbox modal with step-by-step instructions
- **NEW: Font Awesome Icons Integration** - Professional icon system throughout the interface
- **NEW: Branding Color Variables** - Consistent design system with customizable color scheme
- **NEW: Maintenance Modes System** - Complete maintenance mode functionality with multiple options
  - 🔧 Maintenance Mode (HTTP 503) for scheduled maintenance
  - 🚀 Coming Soon Mode (HTTP 200) for new websites
  - 💰 Payment Request Mode (HTTP 402) for pending payments
  - Social contact integration (email, phone, WhatsApp, Skype)
  - Custom messaging and CSS styling support
  - SEO-friendly with proper status codes and noindex tags
  - Administrator and bypass URL access
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
