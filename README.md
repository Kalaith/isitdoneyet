# Is It Done Yet?

A recursive project management application that helps you break down complex tasks into manageable subtasks until completion.

## 🌐 Live Application

**Production Site**: https://webhatchery.au/isitdoneyet/
**Backend API**: https://webhatchery.au/isitdoneyet/backend/public/api

The application is currently hosted on Apache at webhatchery.au and ready for use!

## 🌐 Access

- **Application**: https://webhatchery.au/isitdoneyet/
- **Backend API**: https://webhatchery.au/isitdoneyet/backend/public/api

## 🏗️ Architecture

The application is a vanilla JavaScript frontend that communicates with a PHP backend API. 

### Production Deployment
- **Server**: Apache on webhatchery.au
- **Frontend**: Static files served directly by Apache
- **Backend**: PHP application with Slim framework
- **Database**: MySQL/MariaDB with persistent storage

### Files Structure
```
isitdoneyet/
├── index.html          # Main HTML file
├── app.js              # Application logic
├── api-service.js      # API communication layer
├── style.css           # Styling
└── backend/            # PHP backend application
    ├── public/         # Web-accessible files
    ├── src/            # Application source code
    └── vendor/         # Composer dependencies
```

### Key Components

- **API Service** (`api-service.js`): Handles all communication with the backend
- **Application Logic** (`app.js`): Manages UI state, project hierarchy, and user interactions
- **Responsive Design** (`style.css`): Modern CSS with CSS variables and responsive layout

## 🔌 Backend Integration

The frontend communicates with the backend API at `https://webhatchery.au/isitdoneyet/backend/public/api`.

### API Endpoints Used

- `GET /api/projects` - Load all projects
- `POST /api/projects` - Create new project
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project
- `POST /api/projects/{id}/complete` - Mark project complete
- `POST /api/projects/{id}/subtasks` - Add subtask

## 🎯 Features

- **Hierarchical Task Management**: Unlimited nesting of tasks and subtasks
- **Progress Tracking**: Visual progress bars based on completed subtasks
- **Completion Propagation**: Parent tasks auto-complete when all children are done
- **Responsive Design**: Works on desktop and mobile devices
- **Real-time Updates**: Changes are immediately saved to the backend
- **Intuitive UI**: Modal-based task details with simple yes/no completion flow

## 🚀 Deployment

The application is currently deployed on Apache at webhatchery.au. For deployment details and configuration, see `APACHE-DEPLOYMENT.md` and `DEPLOYMENT.md`.

### Production Environment
- **Server**: Apache with mod_rewrite enabled
- **PHP**: Version 8.0+ with required extensions
- **Database**: MySQL/MariaDB with proper schema
- **SSL**: HTTPS enabled with valid certificate

## 🛠️ Development

### CORS Configuration
CORS is configured for the webhatchery.au domain to allow secure API communication.

### Error Handling
- API errors are logged to the browser console
- User-friendly error messages for network issues
- Graceful fallback behavior for offline usage

## 🎨 Customization

### Styling
Edit `style.css` to customize the appearance. The design uses CSS custom properties for easy theming:

```css
:root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
}
```

### API Configuration
The application uses the production API at `https://webhatchery.au/isitdoneyet/backend/public/api`.

To customize the API URL, update the base URL in `api-service.js`:

```javascript
constructor() {
    this.baseURL = 'https://webhatchery.au/isitdoneyet/backend/public/api';
}
```

## 📱 Browser Compatibility

- Modern browsers with ES6+ support
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## 🐛 Troubleshooting

If you encounter issues with the application:
- Check the browser console for errors
- Verify your internet connection
- Try clearing browser cache and cookies
- Ensure webhatchery.au is accessible
- Report persistent issues via the project repository

### Data Not Persisting
- Data is automatically saved to the live database
- Check browser console for API errors
- Verify the backend API is responding correctly

## 📄 License

MIT License - see backend README for full details.
