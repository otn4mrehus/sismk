# Sistem Galeri Gambar/Foto - Specification

## 1. Project Overview
- **Project Name**: Galeri Foto SPA
- **Type**: Single Page Application (PHP Native 7.4)
- **Core Functionality**: Gallery system with CSV-based storage, image upload with compression, multi-user RBAC without login
- **Target Users**: Users who want to share photos publicly or privately

## 2. UI/UX Specification

### Layout Structure
- **Header**: Logo, Navigation (Home, Galeri, Upload, Login/Admin), User info
- **Hero Section**: Welcome message with "Upload Gambar/Foto" button
- **Content**: Gallery grid, Upload form, Login form
- **Footer**: Copyright info

### Responsive Breakpoints
- Mobile: < 576px (1 column grid)
- Tablet: 576px - 992px (2-3 column grid)
- Desktop: > 992px (4-5 column grid)

### Visual Design
- **Color Palette**:
  - Primary: #2563eb (Blue)
  - Secondary: #1e293b (Dark Slate)
  - Accent: #f59e0b (Amber)
  - Background: #f8fafc (Light Gray)
  - Card: #ffffff (White)
  - Text: #334155 (Slate)
- **Typography**: 
  - Font: 'Poppins', sans-serif
  - Headings: 600-700 weight
  - Body: 400 weight
- **Spacing**: 8px base unit
- **Visual Effects**: 
  - Card shadow: 0 4px 6px rgba(0,0,0,0.1)
  - Hover: scale(1.02), shadow increase
  - Transitions: 0.3s ease

### Components
- **Gallery Card**: Image thumbnail, title, category badge, private/public icon
- **Upload Modal**: Form with file input, category select, title, description, private toggle
- **Login Modal**: Username/password fields
- **Admin Panel**: User management, category management
- **Toast Notifications**: Success/error messages

## 3. Functionality Specification

### Core Features

#### Authentication (RBAC)
- **Admin**: Full access - manage users, categories, delete photos
- **User**: Upload photos, manage own photos (edit/delete)
- **Guest**: View public photos only
- **Login**: Simple username validation against server-side CSV

#### User Management (CSV-based)
- Users stored in `data/users.csv`: id,username,password,role,created_at
- Default admin: admin/admin123

#### Gallery
- Photos stored in `data/photos.csv`: id,user_id,username,title,description,category,filename,filepath,is_private,views,created_at
- Display in responsive grid
- Filter by category
- Show private indicator for owner/admin

#### Upload System
- Multi-upload (up to 5 files) or single
- Fields: Category (dropdown), Title, Description, Private/Public toggle
- Image compression to max 500KB (maintain resolution)
- File naming: `{original_name}_{dd-mm-yyyy_H.i}.{ext}`
- Path: `upload/foto/{tahun}/{bulan}/{kategori}/{judul}/{filename}`

#### Categories
- Stored in `data/categories.csv`: id,name,slug,created_at
- Default categories: Alam, Portrait, Teknologi, Seni, Lainnya

### User Interactions
- Click "Upload" → Open upload modal
- Click image → Open lightbox/view
- Click category filter → Filter gallery
- Login → Show admin panel for admin users

## 4. Acceptance Criteria

1. ✅ Page loads without errors on PHP 7.4
2. ✅ Gallery displays in responsive grid
3. ✅ Upload works with compression to 500KB max
4. ✅ File path follows specified format
5. ✅ CSV files store all data correctly
6. ✅ RBAC works (admin vs user vs guest)
7. ✅ Private photos only visible to owner/admin
8. ✅ Mobile responsive layout works
9. ✅ Multi-upload (up to 5) functional
10. ✅ No login required for basic viewing
