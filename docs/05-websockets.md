# WebSocket & Real-time Communication

## Overview

This guide covers implementing real-time communication using Laravel Reverb, Laravel's official first-party WebSocket server. We'll cover event broadcasting, channel authentication, and client-side integration.

## Table of Contents

- [Technology Selection](#technology-selection)
- [Laravel Reverb Setup](#laravel-reverb-setup)
- [Event Broadcasting](#event-broadcasting)
- [Channel Authentication](#channel-authentication)
- [Client-Side Integration](#client-side-integration)
- [Security Considerations](#security-considerations)
- [Performance & Scaling](#performance--scaling)
- [Testing WebSockets](#testing-websockets)

## Technology Selection

### Why Laravel Reverb?

- ✅ **Official Laravel Package**: First-party support and maintenance
- ✅ **Pusher Compatible**: Drop-in replacement for Pusher using the same protocol
- ✅ **Self-Hosted**: No external dependencies or costs
- ✅ **Laravel Integration**: Seamless integration with Laravel's broadcasting system
- ✅ **Real-time Performance**: Built on ReactPHP for high performance

### Alternatives Comparison

| Solution | Pros | Cons | Best For |
|----------|------|------|----------|
| **Laravel Reverb** | Self-hosted, Laravel-native, No costs | Server management required | Development, Small-medium apps |
| **Pusher** | Managed service, Reliable, No setup | Costs, External dependency | Production, High-scale apps |
| **Ably** | Feature-rich, Global edge network | Costs, Learning curve | Enterprise, Global apps |
| **Socket.io + Node.js** | Mature, Flexible | Additional stack complexity | Custom requirements |

## Laravel Reverb Setup

### 1. Installation

```bash
# Install broadcasting with Reverb
php artisan install:broadcasting

# Or install manually
composer require laravel/reverb
php artisan reverb:install
```

### 2. Environment Configuration

Update your `.env` file:

```env
# Broadcasting
BROADCAST_DRIVER=reverb
QUEUE_CONNECTION=database

# Reverb Configuration
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# For production (with SSL)
# REVERB_SCHEME=https
# REVERB_HOST="wss.yourdomain.com"

# Allowed origins (CORS)
REVERB_ALLOWED_ORIGINS="localhost:3000,127.0.0.1:3000"
```

### 3. Configuration Files

The installation creates `config/reverb.php`:

```php
<?php

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOSTNAME'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', 6379),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', 0),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'id' => env('REVERB_APP_ID'),
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'name' => env('APP_NAME'),
                'host' => null,
                'port' => null,
                'allowed_origins' => ['*'],
                'ping_interval' => env('REVERB_PING_INTERVAL', 30),
                'max_message_size' => env('REVERB_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],
    ],
];
```

### 4. Broadcasting Configuration

Update `config/broadcasting.php`:

```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', '127.0.0.1'),
            'port' => env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
            'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
        ],
    ],
    
    // Keep Pusher as fallback option
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com',
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
            'encrypted' => true,
            'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
        ],
        'client_options' => [
            // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
        ],
    ],
],
```

## Event Broadcasting

### 1. Create Broadcastable Events

```bash
php artisan make:event UserNotification
```

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $title,
        public string $message,
        public string $type = 'info'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'timestamp' => now()->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
        ];
    }
}
```

### 2. Role-Based Broadcasting

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $title,
        public string $message,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

### 3. Real-time Chat Events

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $message,
        public string $chatRoom
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->chatRoom),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

### 4. Broadcasting Events from Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Events\UserNotification;
use App\Events\AdminNotification;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function sendToUser(Request $request, User $user)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'sometimes|in:info,success,warning,error',
        ]);

        // Broadcast the notification
        event(new UserNotification(
            $user,
            $request->title,
            $request->message,
            $request->type ?? 'info'
        ));

        return $this->success(null, 'Notification sent successfully');
    }

    public function sendToAdmins(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'sometimes|array',
        ]);

        // Only admins can send admin notifications
        if (!auth()->user()->hasRole('Admin')) {
            return $this->error('Unauthorized', 403);
        }

        event(new AdminNotification(
            $request->title,
            $request->message,
            $request->data ?? []
        ));

        return $this->success(null, 'Admin notification sent successfully');
    }
}
```

## Channel Authentication

### 1. Define Channel Routes

In `routes/channels.php`:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

// User-specific private channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin-only channel
Broadcast::channel('admin-notifications', function ($user) {
    return $user->hasRole('Admin');
});

// Role-based channels
Broadcast::channel('manager-updates', function ($user) {
    return $user->hasRole(['Admin', 'Manager']);
});

// Chat room presence channel
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    // Check if user has access to this chat room
    // You might want to implement room membership logic here
    
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar ? asset("storage/{$user->avatar}") : null,
        'role' => $user->getRoleNames()->first(),
    ];
});

// Project-specific channel
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    // Check if user is a member of this project
    return $user->projects()->where('id', $projectId)->exists();
});

// Department channel
Broadcast::channel('department.{department}', function ($user, $department) {
    return $user->department === $department;
});
```

### 2. Middleware for Broadcasting Authentication

The broadcasting auth route needs to be protected with Sanctum:

```php
// In routes/api.php or routes/web.php
Route::middleware('auth:sanctum')->group(function () {
    Broadcast::routes();
});
```

### 3. Custom Channel Authorization

Create a custom channel authorization class:

```php
<?php

namespace App\Broadcasting;

use App\Models\User;

class ProjectChannel
{
    public function join(User $user, $projectId)
    {
        // Custom logic to determine if user can join project channel
        $project = \App\Models\Project::find($projectId);
        
        if (!$project) {
            return false;
        }

        // Check if user is project member or has admin role
        return $user->hasRole('Admin') || 
               $project->members()->where('user_id', $user->id)->exists();
    }
}

// Register in routes/channels.php
Broadcast::channel('project.{projectId}', ProjectChannel::class);
```

## Client-Side Integration

### 1. Laravel Echo Setup

Install Echo and Pusher JS on your frontend:

```bash
npm install laravel-echo pusher-js
```

### 2. Echo Configuration

```javascript
// resources/js/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Get auth token from your authentication system
const authToken = localStorage.getItem('auth_token'); // or from cookies, etc.

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_REVERB_APP_KEY || 'your-reverb-key',
    wsHost: process.env.MIX_REVERB_HOST || window.location.hostname,
    wsPort: process.env.MIX_REVERB_PORT || 8080,
    wssPort: process.env.MIX_REVERB_PORT || 8080,
    forceTLS: process.env.MIX_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    
    // Authentication for private channels
    auth: {
        headers: {
            Authorization: `Bearer ${authToken}`,
        },
    },
});
```

### 3. Vue.js Integration Example

```vue
<template>
  <div class="notifications">
    <div 
      v-for="notification in notifications" 
      :key="notification.id"
      :class="['notification', `notification--${notification.type}`]"
    >
      <h3>{{ notification.title }}</h3>
      <p>{{ notification.message }}</p>
      <span class="timestamp">{{ formatTime(notification.timestamp) }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NotificationComponent',
  data() {
    return {
      notifications: [],
      userId: null,
    }
  },
  mounted() {
    // Get current user ID
    this.userId = this.$store.state.auth.user.id;
    
    // Listen for user-specific notifications
    window.Echo.private(`App.Models.User.${this.userId}`)
      .listen('.notification', (e) => {
        this.notifications.unshift(e);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
          this.removeNotification(e.id);
        }, 5000);
      });

    // Listen for admin notifications (if user is admin)
    if (this.$store.getters['auth/isAdmin']) {
      window.Echo.private('admin-notifications')
        .listen('.admin.notification', (e) => {
          this.notifications.unshift(e);
        });
    }
  },
  beforeUnmount() {
    // Clean up listeners
    window.Echo.leave(`App.Models.User.${this.userId}`);
    
    if (this.$store.getters['auth/isAdmin']) {
      window.Echo.leave('admin-notifications');
    }
  },
  methods: {
    removeNotification(id) {
      const index = this.notifications.findIndex(n => n.id === id);
      if (index > -1) {
        this.notifications.splice(index, 1);
      }
    },
    formatTime(timestamp) {
      return new Date(timestamp).toLocaleTimeString();
    }
  }
}
</script>
```

### 4. React Integration Example

```jsx
// NotificationComponent.jsx
import { useState, useEffect, useContext } from 'react';
import { AuthContext } from './contexts/AuthContext';

const NotificationComponent = () => {
  const [notifications, setNotifications] = useState([]);
  const { user, token } = useContext(AuthContext);

  useEffect(() => {
    if (!user || !window.Echo) return;

    // Listen for user-specific notifications
    const userChannel = window.Echo.private(`App.Models.User.${user.id}`)
      .listen('.notification', (e) => {
        setNotifications(prev => [e, ...prev]);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
          setNotifications(prev => prev.filter(n => n.id !== e.id));
        }, 5000);
      });

    // Listen for admin notifications
    let adminChannel = null;
    if (user.roles?.includes('Admin')) {
      adminChannel = window.Echo.private('admin-notifications')
        .listen('.admin.notification', (e) => {
          setNotifications(prev => [e, ...prev]);
        });
    }

    // Cleanup
    return () => {
      window.Echo.leave(`App.Models.User.${user.id}`);
      if (adminChannel) {
        window.Echo.leave('admin-notifications');
      }
    };
  }, [user]);

  return (
    <div className="notifications">
      {notifications.map(notification => (
        <div 
          key={notification.id}
          className={`notification notification--${notification.type}`}
        >
          <h3>{notification.title}</h3>
          <p>{notification.message}</p>
          <span className="timestamp">
            {new Date(notification.timestamp).toLocaleTimeString()}
          </span>
        </div>
      ))}
    </div>
  );
};

export default NotificationComponent;
```

### 5. Chat Implementation

```javascript
// Chat component example
class ChatComponent {
  constructor(roomId, userId) {
    this.roomId = roomId;
    this.userId = userId;
    this.messages = [];
    this.users = [];
    
    this.initializeChannel();
  }

  initializeChannel() {
    // Join presence channel for chat room
    this.channel = window.Echo.join(`chat.${this.roomId}`)
      .here((users) => {
        // Users currently in the room
        this.users = users;
        this.updateUsersList();
      })
      .joining((user) => {
        // User joined the room
        this.users.push(user);
        this.updateUsersList();
        this.addSystemMessage(`${user.name} joined the chat`);
      })
      .leaving((user) => {
        // User left the room
        this.users = this.users.filter(u => u.id !== user.id);
        this.updateUsersList();
        this.addSystemMessage(`${user.name} left the chat`);
      })
      .listen('MessageSent', (e) => {
        this.addMessage(e);
      });
  }

  sendMessage(message) {
    // Send message via API
    fetch('/api/v1/chat/send', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.getAuthToken()}`,
      },
      body: JSON.stringify({
        room_id: this.roomId,
        message: message
      })
    });
  }

  addMessage(messageData) {
    this.messages.push(messageData);
    this.renderMessages();
  }

  addSystemMessage(text) {
    this.addMessage({
      id: Date.now(),
      user: { name: 'System' },
      message: text,
      timestamp: new Date().toISOString(),
      isSystem: true
    });
  }

  updateUsersList() {
    // Update UI with current users
  }

  renderMessages() {
    // Update UI with messages
  }

  destroy() {
    if (this.channel) {
      window.Echo.leave(`chat.${this.roomId}`);
    }
  }
}
```

## Security Considerations

### 1. Channel Access Control

```php
// Strict channel authorization
Broadcast::channel('sensitive-data.{userId}', function ($user, $userId) {
    // Only allow access to own data
    if ($user->id !== (int) $userId) {
        return false;
    }
    
    // Additional checks
    if (!$user->hasVerifiedEmail()) {
        return false;
    }
    
    if ($user->status !== 'active') {
        return false;
    }
    
    return true;
});
```

### 2. Rate Limiting for Broadcasting

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class BroadcastRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $key = 'broadcast:' . $request->user()->id;
        
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'error' => 'Too many broadcast requests'
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 10 requests per minute
        
        return $next($request);
    }
}
```

### 3. Data Sanitization

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SecureNotification implements ShouldBroadcast
{
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'title' => strip_tags($this->title),
            'message' => strip_tags($this->message),
            'type' => $this->type,
            'timestamp' => now()->toISOString(),
            // Never broadcast sensitive data
            // 'internal_notes' => $this->internal_notes, // ❌ Don't do this
        ];
    }
}
```

## Performance & Scaling

### 1. Redis Scaling Configuration

Update `.env` for horizontal scaling:

```env
# Enable Redis-based scaling
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb

# Redis configuration for scaling
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### 2. Queue Broadcasting Events

For high-traffic applications, queue broadcasting:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedNotification implements ShouldBroadcast, ShouldQueue
{
    public $queue = 'broadcasts';
    
    // Event implementation...
}
```

### 3. Connection Management

```javascript
// Reconnection logic
window.Echo.connector.pusher.connection.bind('state_change', function(states) {
  if (states.current === 'disconnected') {
    console.log('WebSocket disconnected, attempting to reconnect...');
  }
  
  if (states.current === 'connected') {
    console.log('WebSocket reconnected successfully');
  }
});

// Heartbeat monitoring
setInterval(() => {
  if (window.Echo.connector.pusher.connection.state === 'disconnected') {
    window.Echo.disconnect();
    // Reinitialize Echo
    initializeEcho();
  }
}, 30000); // Check every 30 seconds
```

## Testing WebSockets

### 1. Testing Events

```php
<?php

namespace Tests\Feature;

use App\Events\UserNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebSocketTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_notification_is_broadcasted(): void
    {
        Event::fake();
        
        $user = User::factory()->create();
        
        event(new UserNotification(
            $user,
            'Test Title',
            'Test Message',
            'info'
        ));
        
        Event::assertDispatched(UserNotification::class, function ($event) use ($user) {
            return $event->user->id === $user->id 
                && $event->title === 'Test Title';
        });
    }

    public function test_channel_authorization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        // User can access their own channel
        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-App.Models.User.{$user->id}",
            ]);
        
        $response->assertStatus(200);
        
        // User cannot access other user's channel
        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-App.Models.User.{$otherUser->id}",
            ]);
        
        $response->assertStatus(403);
    }
}
```

### 2. Manual Testing Commands

```bash
# Start Reverb server for testing
php artisan reverb:start --debug

# In another terminal, trigger events
php artisan tinker
>>> event(new App\Events\UserNotification(User::first(), 'Test', 'Message'));

# Monitor Reverb logs for debugging
tail -f storage/logs/laravel.log | grep -i reverb
```

### 3. Browser Testing

Create a test HTML file for quick WebSocket testing:

```html
<!DOCTYPE html>
<html>
<head>
    <title>WebSocket Test</title>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
</head>
<body>
    <div id="messages"></div>
    
    <script>
        const pusher = new Pusher('your-reverb-key', {
            wsHost: '127.0.0.1',
            wsPort: 8080,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            auth: {
                headers: {
                    'Authorization': 'Bearer YOUR_TOKEN_HERE'
                }
            }
        });

        const channel = pusher.subscribe('private-App.Models.User.1');
        
        channel.bind('notification', function(data) {
            const messages = document.getElementById('messages');
            messages.innerHTML += `<p>${data.title}: ${data.message}</p>`;
        });

        pusher.connection.bind('state_change', function(states) {
            console.log('Connection state changed:', states);
        });
    </script>
</body>
</html>
```

---

**Next**: [Excel Import/Export Operations](./06-excel-operations.md)