# Is It Done Yet? - Frontend

A recursive project management application that helps you break down complex tasks into manageable subtasks until completion.

## 🚀 Quick Start

### Option 1: PowerShell (Recommended)
```powershell
.\start-frontend.ps1
```

### Option 2: Batch File
```cmd
start-frontend.bat
```

### Option 3: Manual Commands

**With Node.js:**
```powershell
node server.js
```

**With Python:**
```powershell
python -m http.server 3000
```

**With PHP:**
```powershell
php -S localhost:3000
```

## 📦 Package Manager Setup

If you have Node.js installed, you can also use npm:

```powershell
# Install dependencies (optional)
npm install

# Start frontend server
npm start

# Start both frontend and backend
npm run full-stack
```

## 🌐 Access

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:3001/api (must be running separately)

## 🏗️ Architecture

The frontend is a vanilla JavaScript application that communicates with a PHP backend API:

### Files Structure
```
isitdoneyet/
├── index.html          # Main HTML file
├── app.js              # Application logic
├── api-service.js      # API communication layer
├── style.css           # Styling
├── server.js           # Node.js development server
├── start-frontend.ps1  # PowerShell startup script
├── start-frontend.bat  # Batch startup script
└── package.json        # Node.js configuration
```

### Key Components

- **API Service** (`api-service.js`): Handles all communication with the backend
- **Application Logic** (`app.js`): Manages UI state, project hierarchy, and user interactions
- **Responsive Design** (`style.css`): Modern CSS with CSS variables and responsive layout

## 🔌 Backend Integration

The frontend expects the backend API to be running on `http://localhost:3001/api`. 

To start the backend:
```powershell
cd backend
composer start
```

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

## 🛠️ Development

### Hot Reloading
The development servers support hot reloading - just save your files and refresh the browser.

### CORS Configuration
The frontend includes CORS headers for development. The backend should also be configured with appropriate CORS settings.

### Error Handling
- API errors are logged to the browser console
- User-friendly error messages for network issues
- Fallback to local storage if backend is unavailable

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
Update the base URL in `api-service.js` if your backend runs on a different port:

```javascript
constructor() {
    this.baseURL = 'http://localhost:3001/api';
}
```

## 📱 Browser Compatibility

- Modern browsers with ES6+ support
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## 🐛 Troubleshooting

### Frontend Won't Start
- Ensure you have Node.js, Python, or PHP installed
- Check if port 3000 is already in use
- Try running the manual commands

### Can't Connect to Backend
- Verify backend is running on port 3001
- Check CORS configuration
- Ensure database is initialized

### Data Not Persisting
- Confirm backend API is accessible
- Check browser console for API errors
- Verify database connection in backend

## 📄 License

MIT License - see backend README for full details.
