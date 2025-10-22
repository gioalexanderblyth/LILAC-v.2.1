// Dashboard functionality for LILAC Document Management System
class DashboardManager {
    constructor() {
        this.init();
    }

    init() {
        // Check authentication
        this.checkAuthentication();
        
        // Initialize dashboard components
        this.updateCurrentDate();
        this.updateUserInfo();
        this.setupEventListeners();
    }

    checkAuthentication() {
        const savedUser = localStorage.getItem("lilac_user") || sessionStorage.getItem("lilac_user");
        if (!savedUser) {
            // Redirect to login if not authenticated
            window.location.href = "pages/index.html";
            return;
        }
        
        this.currentUser = JSON.parse(savedUser);
    }

    updateCurrentDate() {
        const dateElement = document.getElementById("currentDate");
        if (dateElement) {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            dateElement.textContent = now.toLocaleDateString('en-US', options);
        }
    }

    updateUserInfo() {
        const userInfoElement = document.getElementById("userInfo");
        if (userInfoElement && this.currentUser) {
            userInfoElement.textContent = this.currentUser.username;
        }
    }

    setupEventListeners() {
        // Add event listeners for quick action buttons
        const quickActionButtons = document.querySelectorAll('.grid button');
        quickActionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const buttonText = e.currentTarget.querySelector('p').textContent;
                this.handleQuickAction(buttonText);
            });
        });
    }

    handleQuickAction(action) {
        switch(action) {
            case 'Upload Document':
                this.showNotification('Upload Document feature coming soon!', 'info');
                break;
            case 'Search Documents':
                this.showNotification('Search Documents feature coming soon!', 'info');
                break;
            case 'Create Folder':
                this.showNotification('Create Folder feature coming soon!', 'info');
                break;
            case 'Share Document':
                this.showNotification('Share Document feature coming soon!', 'info');
                break;
            default:
                this.showNotification('Feature coming soon!', 'info');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
        
        // Set colors based on type
        switch(type) {
            case 'success':
                notification.classList.add('bg-green-100', 'border-green-400', 'text-green-700', 'border');
                break;
            case 'error':
                notification.classList.add('bg-red-100', 'border-red-400', 'text-red-700', 'border');
                break;
            case 'warning':
                notification.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700', 'border');
                break;
            default:
                notification.classList.add('bg-blue-100', 'border-blue-400', 'text-blue-700', 'border');
        }
        
        notification.innerHTML = `
            <div class="flex items-center">
                <span class="material-symbols-outlined mr-2">info</span>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }, 3000);
    }
}

// Global logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear user data
        localStorage.removeItem("lilac_user");
        sessionStorage.removeItem("lilac_user");
        
        // Redirect to login
        window.location.href = "index.html";
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
    window.dashboardManager = new DashboardManager();
});
