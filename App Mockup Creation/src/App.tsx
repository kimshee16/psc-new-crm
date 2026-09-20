import { useState, useCallback, useRef } from 'react'
import {
  BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts'

// ── Icons (inline SVG helpers) ──────────────────────────────────────────────
const Icon = ({ d, size = 16, className = '' }: { d: string; size?: number; className?: string }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
    strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" className={className}>
    <path d={d} />
  </svg>
)

const icons = {
  dashboard: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10',
  mail: 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z M22 6l-10 7L2 6',
  docs: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6',
  report: 'M18 20V10 M12 20V4 M6 20v-6',
  admin: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
  chevronLeft: 'M15 18l-6-6 6-6',
  chevronRight: 'M9 18l6-6-6-6',
  chevronDown: 'M6 9l6 6 6-6',
  inbox: 'M22 12h-6l-2 3h-4l-2-3H2 M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z',
  compose: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7 M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
  send: 'M22 2L11 13 M22 2l-7 20-4-9-9-4 20-7z',
  template: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M16 13H8 M16 17H8 M10 9H8',
  upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4 M17 8l-5-5-5 5 M12 3v12',
  folder: 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z',
  file: 'M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z M13 2v7h7',
  trash: 'M3 6h18 M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  edit: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7 M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
  eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
  download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4 M7 10l5 5 5-5 M12 15V3',
  users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75 M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z',
  settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
  link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71 M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
  x: 'M18 6L6 18 M6 6l12 12',
  plus: 'M12 5v14 M5 12h14',
  check: 'M20 6L9 17l-5-5',
  alert: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z M12 9v4 M12 17h.01',
  paperclip: 'M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48',
  filter: 'M22 3H2l8 9.46V19l4 2v-8.54L22 3z',
  google: 'M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z',
  bounce: 'M19 14l-7 7m0 0l-7-7m7 7V3',
  search: 'M11 17.25a6.25 6.25 0 1 1 0-12.5 6.25 6.25 0 0 1 0 12.5z M16 16l4.5 4.5',
  print: 'M6 9V2h12v7 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v8H6z',
}

// ── Types ───────────────────────────────────────────────────────────────────
type Section = 'dashboard' | 'communication' | 'documents' | 'reporting' | 'admin'
type CommTab = 'inbox' | 'compose' | 'bulk' | 'bounce' | 'templates'
type DocsTab = 'files' | 'upload'
type ReportTab = 'overview' | 'activity'
type AdminTab = 'access' | 'integrations' | 'templates'

// ── Sidebar ─────────────────────────────────────────────────────────────────
const navItems: { id: Section; label: string; icon: string }[] = [
  { id: 'dashboard', label: 'Dashboard', icon: icons.dashboard },
  { id: 'communication', label: 'Communication', icon: icons.mail },
  { id: 'documents', label: 'Documents', icon: icons.docs },
  { id: 'reporting', label: 'Reporting', icon: icons.report },
  { id: 'admin', label: 'Admin', icon: icons.admin },
]

function Sidebar({ active, onSelect, collapsed, onToggle }: {
  active: Section; onSelect: (s: Section) => void; collapsed: boolean; onToggle: () => void
}) {
  return (
    <aside style={{ width: collapsed ? 64 : 210, minWidth: collapsed ? 64 : 210 }}
      className="flex flex-col h-screen bg-[#1d2260] text-white transition-all duration-200 flex-shrink-0">
      <div className="flex items-center justify-end px-4 py-5 border-b border-white/10">
        <button onClick={onToggle}
          className="p-1 rounded hover:bg-white/10 text-white/60 hover:text-white transition-colors ml-auto">
          <Icon d={collapsed ? icons.chevronRight : icons.chevronLeft} size={14} />
        </button>
      </div>
      <nav className="flex-1 py-3 overflow-y-auto">
        {navItems.map(item => (
          <button key={item.id} onClick={() => onSelect(item.id)}
            className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors
              ${active === item.id ? 'bg-[#4f52c4] text-white' : 'text-white/60 hover:bg-white/8 hover:text-white'}`}>
            <Icon d={item.icon} size={16} className="flex-shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
          </button>
        ))}
      </nav>
      {!collapsed && (
        <div className="px-4 py-3 text-[10px] text-white/30 border-t border-white/10">
          Version 2.0.0<br />© 2026 New PSC Portal
        </div>
      )}
    </aside>
  )
}

// ── Shared UI atoms ──────────────────────────────────────────────────────────
function PageHeader({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="mb-6">
      <h1 className="text-2xl font-bold text-[#1a1d3b]">{title}</h1>
      <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>
    </div>
  )
}

function Tabs<T extends string>({ tabs, active, onChange }: {
  tabs: { id: T; label: string }[]; active: T; onChange: (t: T) => void
}) {
  return (
    <div className="flex border-b border-gray-200 mb-6">
      {tabs.map(t => (
        <button key={t.id} onClick={() => onChange(t.id)}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors -mb-px
            ${active === t.id ? 'border-[#4f52c4] text-[#4f52c4]' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
          {t.label}
        </button>
      ))}
    </div>
  )
}

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return <div className={`bg-white rounded-lg border border-gray-200 ${className}`}>{children}</div>
}

function Badge({ label, color }: { label: string; color: 'green' | 'red' | 'orange' | 'blue' | 'gray' }) {
  const colors = {
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    orange: 'bg-amber-50 text-amber-700 ring-amber-200',
    blue: 'bg-blue-50 text-blue-700 ring-blue-200',
    gray: 'bg-gray-100 text-gray-600 ring-gray-200',
  }
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ${colors[color]}`}>
      {color === 'green' && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block" />}
      {color === 'red' && <span className="w-1.5 h-1.5 rounded-full bg-red-500 inline-block" />}
      {label}
    </span>
  )
}

function PrimaryBtn({ children, onClick, icon }: { children: React.ReactNode; onClick?: () => void; icon?: string }) {
  return (
    <button onClick={onClick}
      className="flex items-center gap-2 px-4 py-2 bg-[#4f52c4] text-white text-sm font-medium rounded-md hover:bg-[#3d40aa] transition-colors">
      {icon && <Icon d={icon} size={14} />}
      {children}
    </button>
  )
}

function SecondaryBtn({ children, onClick, icon }: { children: React.ReactNode; onClick?: () => void; icon?: string }) {
  return (
    <button onClick={onClick}
      className="flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
      {icon && <Icon d={icon} size={14} />}
      {children}
    </button>
  )
}

function Input({ placeholder, value, onChange, type = 'text', className = '' }: {
  placeholder?: string; value?: string; onChange?: (v: string) => void; type?: string; className?: string
}) {
  return (
    <input type={type} placeholder={placeholder} value={value}
      onChange={e => onChange?.(e.target.value)}
      className={`border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#4f52c4]/30 focus:border-[#4f52c4] ${className}`} />
  )
}

function Select({ value, onChange, options, className = '' }: {
  value: string; onChange: (v: string) => void; options: { label: string; value: string }[]; className?: string
}) {
  return (
    <select value={value} onChange={e => onChange(e.target.value)}
      className={`border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#4f52c4]/30 bg-white ${className}`}>
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )
}

// ── DASHBOARD ────────────────────────────────────────────────────────────────
function Dashboard({ onNav }: { onNav: (s: Section) => void }) {
  const stats = [
    { label: 'Total Clients', value: '1,284', icon: icons.users, color: 'bg-indigo-50 text-indigo-600' },
    { label: 'Emails Sent (30d)', value: '3,917', icon: icons.send, color: 'bg-blue-50 text-blue-600' },
    { label: 'Documents Stored', value: '8,542', icon: icons.docs, color: 'bg-emerald-50 text-emerald-600' },
    { label: 'Open Cases', value: '47', icon: icons.alert, color: 'bg-amber-50 text-amber-600' },
  ]
  const quickActions = [
    { label: 'Compose Email', sub: 'Send or schedule a new email', icon: icons.compose, color: 'bg-indigo-50 text-indigo-600', section: 'communication' as Section },
    { label: 'Upload Documents', sub: 'Drag and drop or browse files', icon: icons.upload, color: 'bg-blue-50 text-blue-600', section: 'documents' as Section },
    { label: 'Generate Report', sub: 'Activity, email, and file reports', icon: icons.report, color: 'bg-emerald-50 text-emerald-600', section: 'reporting' as Section },
    { label: 'Manage Access', sub: 'Configure user roles and rights', icon: icons.admin, color: 'bg-violet-50 text-violet-600', section: 'admin' as Section },
    { label: 'Send Bulk Email', sub: 'Broadcast to client segments', icon: icons.send, color: 'bg-pink-50 text-pink-600', section: 'communication' as Section },
    { label: 'Manage Templates', sub: 'Edit admin email templates', icon: icons.template, color: 'bg-amber-50 text-amber-600', section: 'admin' as Section },
  ]
  const activity = [
    { action: 'Email sent to Sarah Chen', time: '2 min ago', type: 'email' },
    { action: 'Document "Passport_Liu.pdf" uploaded', time: '15 min ago', type: 'doc' },
    { action: 'Bulk email dispatched to 142 clients', time: '1 hr ago', type: 'bulk' },
    { action: 'Bounce logged for j.doe@mail.com', time: '2 hr ago', type: 'bounce' },
    { action: 'Report "Monthly Activity" generated', time: '3 hr ago', type: 'report' },
    { action: 'New user access granted to M. Torres', time: '5 hr ago', type: 'admin' },
    { action: 'Template "Visa Approval" updated', time: '1 day ago', type: 'template' },
    { action: 'Folder "2026 Applications" created', time: '1 day ago', type: 'doc' },
  ]
  return (
    <div>
      <PageHeader title="Welcome to New PSC Portal" subtitle="Manage communications, documents, reporting, and administration" />
      <div className="grid grid-cols-4 gap-4 mb-6">
        {stats.map(s => (
          <Card key={s.label} className="p-4 flex items-start justify-between">
            <div>
              <div className="text-xs text-gray-500 mb-1">{s.label}</div>
              <div className="text-2xl font-bold text-[#1a1d3b]">{s.value}</div>
            </div>
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${s.color}`}>
              <Icon d={s.icon} size={18} />
            </div>
          </Card>
        ))}
      </div>
      <div className="mb-2 font-semibold text-[#1a1d3b]">Quick Actions</div>
      <div className="grid grid-cols-3 gap-4 mb-6">
        {quickActions.map(a => (
          <button key={a.label} onClick={() => onNav(a.section)}
            className="flex items-center gap-3 p-4 bg-white rounded-lg border border-gray-200 hover:border-[#4f52c4]/40 hover:shadow-sm transition-all text-left">
            <div className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${a.color}`}>
              <Icon d={a.icon} size={16} />
            </div>
            <div>
              <div className="text-sm font-medium text-[#1a1d3b]">{a.label}</div>
              <div className="text-xs text-gray-500">{a.sub}</div>
            </div>
          </button>
        ))}
      </div>
      <Card>
        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div>
            <div className="font-semibold text-[#1a1d3b]">Recent Activity</div>
            <div className="text-xs text-gray-400">{activity.length} events</div>
          </div>
          <Icon d={icons.chevronRight} size={16} className="text-gray-400" />
        </div>
        <div className="divide-y divide-gray-50">
          {activity.map((a, i) => (
            <div key={i} className="flex items-center justify-between px-5 py-3">
              <span className="text-sm text-gray-700">{a.action}</span>
              <span className="text-xs text-gray-400 ml-4 flex-shrink-0">{a.time}</span>
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}

// ── COMMUNICATION ────────────────────────────────────────────────────────────
const sampleEmails = [
  { id: 1, from: 'sarah.chen@example.com', subject: 'Visa Application Update', preview: 'Dear Ms Chen, your application has been reviewed...', date: 'Jul 20', read: false, status: 'received' },
  { id: 2, from: 'client@domain.com', subject: 'Document Request', preview: 'Please find attached the requested passport copy...', date: 'Jul 19', read: true, status: 'received' },
  { id: 3, from: 'admin@immi.gov.au', subject: 'Case 2026-0041 — Decision', preview: 'We are pleased to advise that your client...', date: 'Jul 18', read: true, status: 'received' },
  { id: 4, from: 'noreply@linkedin.com', subject: 'New connection request', preview: 'You have a new connection request from...', date: 'Jul 17', read: true, status: 'received' },
]

const sampleTemplates = [
  { id: 1, name: 'Visa Approval Notification', subject: 'Your visa application has been approved', placeholders: ['{{client_name}}', '{{visa_type}}', '{{approval_date}}'] },
  { id: 2, name: 'Document Request', subject: 'Action required: Documents needed for your case', placeholders: ['{{client_name}}', '{{doc_list}}', '{{deadline}}'] },
  { id: 3, name: 'Appointment Reminder', subject: 'Reminder: Upcoming appointment on {{date}}', placeholders: ['{{client_name}}', '{{date}}', '{{time}}', '{{location}}'] },
  { id: 4, name: 'Case Status Update', subject: 'Update on your immigration case {{case_id}}', placeholders: ['{{client_name}}', '{{case_id}}', '{{status}}', '{{next_steps}}'] },
]

const sampleBounces = [
  { email: 'j.doe@oldmail.com', reason: 'User unknown', date: 'Jul 20, 2026', severity: 'Hard' },
  { email: 'wang.li@defunct.net', reason: 'Mailbox full', date: 'Jul 18, 2026', severity: 'Soft' },
  { email: 'no-reply@domain.org', reason: 'Domain not found', date: 'Jul 15, 2026', severity: 'Hard' },
]

function Communication() {
  const [tab, setTab] = useState<CommTab>('inbox')
  const [selected, setSelected] = useState<number | null>(null)
  const [showPreview, setShowPreview] = useState(false)
  const [editTemplate, setEditTemplate] = useState<number | null>(null)
  const [composeData, setComposeData] = useState({ to: '', subject: '', body: '', cc: '' })
  const [dirty, setDirty] = useState(false)
  const [showWarning, setShowWarning] = useState(false)

  const handleTabChange = (t: CommTab) => {
    if (dirty && tab === 'compose') { setShowWarning(true); return }
    setTab(t)
  }

  const tabs = [
    { id: 'inbox' as CommTab, label: 'Inbox' },
    { id: 'compose' as CommTab, label: 'Compose' },
    { id: 'bulk' as CommTab, label: 'Bulk Email' },
    { id: 'bounce' as CommTab, label: 'Bounce Log' },
    { id: 'templates' as CommTab, label: 'Email Templates' },
  ]

  return (
    <div>
      <PageHeader title="Communication" subtitle="Send, receive, and manage all client communications" />

      {showWarning && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <Card className="p-6 max-w-sm w-full mx-4 shadow-xl">
            <div className="flex items-start gap-3 mb-4">
              <div className="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                <Icon d={icons.alert} size={18} className="text-amber-600" />
              </div>
              <div>
                <div className="font-semibold text-[#1a1d3b]">Unsaved Changes</div>
                <div className="text-sm text-gray-500 mt-1">You have unsaved changes in your draft. Do you want to leave without saving?</div>
              </div>
            </div>
            <div className="flex gap-3 justify-end">
              <SecondaryBtn onClick={() => setShowWarning(false)}>Stay on page</SecondaryBtn>
              <button onClick={() => { setDirty(false); setShowWarning(false); setTab('inbox') }}
                className="px-4 py-2 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition-colors">
                Discard & Leave
              </button>
            </div>
          </Card>
        </div>
      )}

      <Tabs tabs={tabs} active={tab} onChange={handleTabChange} />

      {tab === 'inbox' && (
        <div className="flex gap-4">
          <Card className="flex-1 min-w-0 overflow-hidden">
            <div className="p-3 border-b border-gray-100 flex items-center gap-2">
              <Input placeholder="Search emails..." className="flex-1" />
              <SecondaryBtn icon={icons.filter}>Filter</SecondaryBtn>
              <div className="flex items-center gap-1 text-xs text-gray-500 ml-2 bg-gray-50 border border-gray-200 rounded px-2 py-1">
                <svg width={12} height={12} viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z" fill="#1DA1F2" /></svg>
                Gmail Connected
              </div>
            </div>
            <div className="divide-y divide-gray-50">
              {sampleEmails.map(e => (
                <div key={e.id} onClick={() => { setSelected(e.id); setShowPreview(true) }}
                  className={`flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors
                    ${selected === e.id ? 'bg-indigo-50/50' : ''} ${!e.read ? 'font-medium' : ''}`}>
                  <div className="w-8 h-8 rounded-full bg-[#4f52c4]/15 flex items-center justify-center flex-shrink-0 text-xs font-bold text-[#4f52c4]">
                    {e.from[0].toUpperCase()}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-sm text-[#1a1d3b] truncate">{e.from}</span>
                      <span className="text-xs text-gray-400 flex-shrink-0">{e.date}</span>
                    </div>
                    <div className="text-sm truncate">{e.subject}</div>
                    <div className="text-xs text-gray-400 truncate">{e.preview}</div>
                  </div>
                  {!e.read && <div className="w-2 h-2 rounded-full bg-[#4f52c4] mt-2 flex-shrink-0" />}
                </div>
              ))}
            </div>
          </Card>
          {showPreview && selected && (
            <Card className="w-96 flex-shrink-0 flex flex-col">
              <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                <span className="font-semibold text-sm text-[#1a1d3b]">Email Preview</span>
                <button onClick={() => setShowPreview(false)} className="text-gray-400 hover:text-gray-600">
                  <Icon d={icons.x} size={14} />
                </button>
              </div>
              <div className="p-4 flex-1 overflow-y-auto">
                {(() => {
                  const e = sampleEmails.find(x => x.id === selected)!
                  return (
                    <>
                      <div className="mb-3">
                        <div className="text-xs text-gray-400 mb-0.5">From</div>
                        <div className="text-sm font-medium">{e.from}</div>
                      </div>
                      <div className="mb-3">
                        <div className="text-xs text-gray-400 mb-0.5">Subject</div>
                        <div className="text-sm font-semibold">{e.subject}</div>
                      </div>
                      <div className="mb-4">
                        <div className="text-xs text-gray-400 mb-0.5">Date</div>
                        <div className="text-sm">{e.date}</div>
                      </div>
                      <div className="border-t border-gray-100 pt-3 text-sm text-gray-700 leading-relaxed">
                        {e.preview} Lorem ipsum dolor sit amet consectetur adipiscing elit. Nullam feugiat tortor sit amet dolor consectetur, at dictum tortor faucibus.
                      </div>
                      <div className="mt-4 flex gap-2">
                        <PrimaryBtn icon={icons.compose}>Reply</PrimaryBtn>
                        <SecondaryBtn icon={icons.print}>Print</SecondaryBtn>
                      </div>
                    </>
                  )
                })()}
              </div>
            </Card>
          )}
        </div>
      )}

      {tab === 'compose' && (
        <Card className="max-w-2xl p-6">
          <div className="font-semibold text-[#1a1d3b] mb-4">New Email</div>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <label className="text-sm text-gray-500 w-14 flex-shrink-0">To</label>
              <Input placeholder="recipient@email.com" className="flex-1" value={composeData.to}
                onChange={v => { setComposeData(d => ({ ...d, to: v })); setDirty(true) }} />
            </div>
            <div className="flex items-center gap-3">
              <label className="text-sm text-gray-500 w-14 flex-shrink-0">CC</label>
              <Input placeholder="cc@email.com" className="flex-1" value={composeData.cc}
                onChange={v => { setComposeData(d => ({ ...d, cc: v })); setDirty(true) }} />
            </div>
            <div className="flex items-center gap-3">
              <label className="text-sm text-gray-500 w-14 flex-shrink-0">Subject</label>
              <Input placeholder="Email subject" className="flex-1" value={composeData.subject}
                onChange={v => { setComposeData(d => ({ ...d, subject: v })); setDirty(true) }} />
            </div>
            <div className="flex gap-3">
              <div className="w-14 flex-shrink-0" />
              <textarea placeholder="Write your message here..."
                value={composeData.body}
                onChange={e => { setComposeData(d => ({ ...d, body: e.target.value })); setDirty(true) }}
                rows={8}
                className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#4f52c4]/30 focus:border-[#4f52c4] resize-none" />
            </div>
            <div className="flex items-center gap-3 pt-2 border-t border-gray-100">
              <div className="w-14" />
              <div className="flex gap-2 flex-wrap">
                <PrimaryBtn icon={icons.send}>Send</PrimaryBtn>
                <SecondaryBtn icon={icons.paperclip}>Attach File</SecondaryBtn>
                <SecondaryBtn icon={icons.template}>Use Template</SecondaryBtn>
                <SecondaryBtn icon={icons.eye}>Preview</SecondaryBtn>
                <SecondaryBtn>Save Draft</SecondaryBtn>
              </div>
            </div>
          </div>
        </Card>
      )}

      {tab === 'bulk' && (
        <div className="space-y-4">
          <Card className="p-5">
            <div className="font-semibold text-[#1a1d3b] mb-4">Bulk Email Campaign</div>
            <div className="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Recipient Segment</label>
                <Select value="all" onChange={() => {}} options={[
                  { value: 'all', label: 'All Active Clients' },
                  { value: 'visa', label: 'Visa Applicants' },
                  { value: 'pending', label: 'Pending Cases' },
                  { value: 'approved', label: 'Approved (Last 30d)' },
                ]} className="w-full" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Email Template</label>
                <Select value="1" onChange={() => {}} options={sampleTemplates.map(t => ({ value: String(t.id), label: t.name }))} className="w-full" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Schedule</label>
                <Input type="datetime-local" className="w-full" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">From Name</label>
                <Input placeholder="New PSC Portal Team" className="w-full" />
              </div>
            </div>
            <div className="bg-indigo-50 border border-indigo-200 rounded-md px-4 py-3 text-sm text-indigo-700 mb-4">
              <strong>1,284 recipients</strong> match the selected segment. Each will receive a personalised email with their name and case details auto-filled.
            </div>
            <div className="flex gap-2">
              <PrimaryBtn icon={icons.send}>Send Campaign</PrimaryBtn>
              <SecondaryBtn icon={icons.eye}>Preview Email</SecondaryBtn>
              <SecondaryBtn>Save as Draft</SecondaryBtn>
            </div>
          </Card>
          <Card>
            <div className="px-5 py-4 border-b border-gray-100 font-semibold text-sm text-[#1a1d3b]">Campaign History</div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                  <tr>
                    {['Campaign', 'Sent', 'Recipients', 'Open Rate', 'Status', 'Date'].map(h => (
                      <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {[
                    { name: 'Visa Renewal Reminder', sent: 892, total: 912, rate: '71%', status: 'Delivered', date: 'Jul 15' },
                    { name: 'Q2 Newsletter', sent: 1240, total: 1284, rate: '58%', status: 'Delivered', date: 'Jul 1' },
                    { name: 'Document Alert', sent: 143, total: 143, rate: '89%', status: 'Delivered', date: 'Jun 22' },
                  ].map(r => (
                    <tr key={r.name} className="hover:bg-gray-50">
                      <td className="px-4 py-3 font-medium text-[#1a1d3b]">{r.name}</td>
                      <td className="px-4 py-3">{r.sent}</td>
                      <td className="px-4 py-3">{r.total}</td>
                      <td className="px-4 py-3 font-medium text-emerald-600">{r.rate}</td>
                      <td className="px-4 py-3"><Badge label={r.status} color="green" /></td>
                      <td className="px-4 py-3 text-gray-400">{r.date}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}

      {tab === 'bounce' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between mb-2">
            <div className="text-sm text-gray-500">{sampleBounces.length} bounced addresses on record</div>
            <PrimaryBtn icon={icons.bounce}>Log Bounce</PrimaryBtn>
          </div>
          <Card>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                  <tr>
                    {['Email Address', 'Reason', 'Severity', 'Date', 'Actions'].map(h => (
                      <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {sampleBounces.map(b => (
                    <tr key={b.email} className="hover:bg-gray-50">
                      <td className="px-4 py-3 font-mono text-xs text-[#1a1d3b]">{b.email}</td>
                      <td className="px-4 py-3 text-gray-600">{b.reason}</td>
                      <td className="px-4 py-3"><Badge label={b.severity} color={b.severity === 'Hard' ? 'red' : 'orange'} /></td>
                      <td className="px-4 py-3 text-gray-400">{b.date}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button className="text-gray-400 hover:text-gray-600"><Icon d={icons.edit} size={14} /></button>
                          <button className="text-gray-400 hover:text-red-500"><Icon d={icons.trash} size={14} /></button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}

      {tab === 'templates' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center mb-2">
            <Input placeholder="Search templates..." className="w-64" />
            <PrimaryBtn icon={icons.plus}>New Template</PrimaryBtn>
          </div>
          {sampleTemplates.map(t => (
            <Card key={t.id} className="p-4">
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="font-medium text-[#1a1d3b] mb-0.5">{t.name}</div>
                  <div className="text-sm text-gray-500 mb-2">Subject: {t.subject}</div>
                  <div className="flex flex-wrap gap-1.5">
                    {t.placeholders.map(p => (
                      <span key={p} className="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs rounded font-mono">{p}</span>
                    ))}
                  </div>
                </div>
                <div className="flex gap-2 flex-shrink-0">
                  <SecondaryBtn icon={icons.eye} onClick={() => setEditTemplate(t.id)}>Preview</SecondaryBtn>
                  <SecondaryBtn icon={icons.edit}>Edit</SecondaryBtn>
                </div>
              </div>
              {editTemplate === t.id && (
                <div className="mt-4 pt-4 border-t border-gray-100">
                  <div className="text-xs font-medium text-gray-500 mb-2">TEMPLATE PREVIEW</div>
                  <div className="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 leading-relaxed border border-gray-200">
                    <div className="font-semibold mb-2">{t.subject}</div>
                    <div>Dear {'{{client_name}}'}, we are writing to inform you regarding your case. {t.name === 'Visa Approval Notification' ? 'Your visa application for {{visa_type}} has been approved on {{approval_date}}.' : 'Please review the details below and contact us if you have any questions.'}</div>
                    <div className="mt-3 text-gray-500">— New PSC Portal Team</div>
                  </div>
                  <button onClick={() => setEditTemplate(null)} className="mt-2 text-xs text-gray-400 hover:text-gray-600">Close preview</button>
                </div>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

// ── DOCUMENTS ────────────────────────────────────────────────────────────────
type FileItem = { id: number; name: string; type: 'file' | 'folder'; size?: string; modified: string; owner: string }

const initialFiles: FileItem[] = [
  { id: 1, name: '2026 Applications', type: 'folder', modified: 'Jul 20, 2026', owner: 'admin' },
  { id: 2, name: 'Passport_Chen_Sarah.pdf', type: 'file', size: '2.3 MB', modified: 'Jul 19, 2026', owner: 'k.torres' },
  { id: 3, name: 'Visa_Grant_Notice.pdf', type: 'file', size: '156 KB', modified: 'Jul 18, 2026', owner: 'admin' },
  { id: 4, name: 'Client Templates', type: 'folder', modified: 'Jul 15, 2026', owner: 'admin' },
  { id: 5, name: 'Employment_Letter_Liu.docx', type: 'file', size: '89 KB', modified: 'Jul 12, 2026', owner: 'm.patel' },
  { id: 6, name: 'Medical_Certificate.pdf', type: 'file', size: '4.1 MB', modified: 'Jul 10, 2026', owner: 'k.torres' },
  { id: 7, name: 'Photo_ID_Wang.jpg', type: 'file', size: '1.2 MB', modified: 'Jul 9, 2026', owner: 'admin' },
  { id: 8, name: 'Archived Cases', type: 'folder', modified: 'Jun 30, 2026', owner: 'admin' },
]

function Documents() {
  const [tab, setTab] = useState<DocsTab>('files')
  const [files, setFiles] = useState<FileItem[]>(initialFiles)
  const [dragging, setDragging] = useState(false)
  const [selected, setSelected] = useState<number | null>(null)
  const [renaming, setRenaming] = useState<number | null>(null)
  const [renameName, setRenameName] = useState('')
  const [sizeLimit, setSizeLimit] = useState('50')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    setDragging(false)
    const dropped = Array.from(e.dataTransfer.files)
    const newFiles: FileItem[] = dropped.map((f, i) => ({
      id: Date.now() + i, name: f.name, type: 'file',
      size: `${(f.size / 1024 / 1024).toFixed(1)} MB`,
      modified: new Date().toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }),
      owner: 'admin'
    }))
    setFiles(prev => [...newFiles, ...prev])
  }, [])

  const startRename = (f: FileItem) => { setRenaming(f.id); setRenameName(f.name) }
  const finishRename = () => {
    if (renaming && renameName.trim()) {
      setFiles(prev => prev.map(f => f.id === renaming ? { ...f, name: renameName.trim() } : f))
    }
    setRenaming(null)
  }
  const deleteFile = (id: number) => {
    if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
      setFiles(prev => prev.filter(f => f.id !== id))
    }
  }

  const tabs = [
    { id: 'files' as DocsTab, label: 'File Manager' },
    { id: 'upload' as DocsTab, label: 'Upload' },
  ]

  return (
    <div>
      <PageHeader title="Documents" subtitle="Manage, upload, preview, and organise all case documents" />
      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'files' && (
        <Card>
          <div className="px-4 py-3 border-b border-gray-100 flex items-center gap-3">
            <Input placeholder="Search files and folders..." className="flex-1" icon={icons.search} />
            <SecondaryBtn icon={icons.filter}>Filter</SecondaryBtn>
            <PrimaryBtn icon={icons.upload} onClick={() => setTab('upload')}>Upload</PrimaryBtn>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                  {['Name', 'Size', 'Modified', 'Owner', 'Actions'].map(h => (
                    <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {files.map(f => (
                  <tr key={f.id} onClick={() => setSelected(f.id)}
                    className={`hover:bg-gray-50 cursor-pointer transition-colors ${selected === f.id ? 'bg-indigo-50/40' : ''}`}>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <Icon d={f.type === 'folder' ? icons.folder : icons.file} size={16}
                          className={f.type === 'folder' ? 'text-amber-500' : 'text-gray-400'} />
                        {renaming === f.id ? (
                          <input value={renameName} onChange={e => setRenameName(e.target.value)}
                            onBlur={finishRename} onKeyDown={e => e.key === 'Enter' && finishRename()}
                            autoFocus className="border-b border-[#4f52c4] text-sm outline-none bg-transparent px-1" />
                        ) : (
                          <span className="font-medium text-[#1a1d3b]">{f.name}</span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-gray-400">{f.size ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-500">{f.modified}</td>
                    <td className="px-4 py-3 text-gray-500">{f.owner}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <button title="Preview" className="text-gray-400 hover:text-[#4f52c4] transition-colors">
                          <Icon d={icons.eye} size={14} />
                        </button>
                        <button title="Download" className="text-gray-400 hover:text-[#4f52c4] transition-colors">
                          <Icon d={icons.download} size={14} />
                        </button>
                        <button title="Print" className="text-gray-400 hover:text-[#4f52c4] transition-colors">
                          <Icon d={icons.print} size={14} />
                        </button>
                        <button title="Rename" onClick={e => { e.stopPropagation(); startRename(f) }}
                          className="text-gray-400 hover:text-amber-500 transition-colors">
                          <Icon d={icons.edit} size={14} />
                        </button>
                        <button title="Delete" onClick={e => { e.stopPropagation(); deleteFile(f.id) }}
                          className="text-gray-400 hover:text-red-500 transition-colors">
                          <Icon d={icons.trash} size={14} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {tab === 'upload' && (
        <div className="space-y-4 max-w-2xl">
          <Card className="p-5">
            <div className="flex items-center justify-between mb-3">
              <div className="font-semibold text-[#1a1d3b]">File Size Limit</div>
              <div className="flex items-center gap-2">
                <Input value={sizeLimit} onChange={setSizeLimit} className="w-20 text-center" />
                <span className="text-sm text-gray-500">MB max</span>
              </div>
            </div>
            <div className="text-xs text-gray-400">Files exceeding this limit will be rejected before upload begins.</div>
          </Card>

          <Card className="p-5">
            <div
              onDragOver={e => { e.preventDefault(); setDragging(true) }}
              onDragLeave={() => setDragging(false)}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`border-2 border-dashed rounded-xl p-12 text-center cursor-pointer transition-all
                ${dragging ? 'border-[#4f52c4] bg-indigo-50' : 'border-gray-200 hover:border-[#4f52c4]/50 hover:bg-gray-50'}`}>
              <input ref={fileInputRef} type="file" multiple className="hidden"
                onChange={e => {
                  const dropped = Array.from(e.target.files ?? [])
                  const newFiles: FileItem[] = dropped.map((f, i) => ({
                    id: Date.now() + i, name: f.name, type: 'file',
                    size: `${(f.size / 1024 / 1024).toFixed(1)} MB`,
                    modified: new Date().toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }),
                    owner: 'admin'
                  }))
                  setFiles(prev => [...newFiles, ...prev])
                  setTab('files')
                }} />
              <div className="flex flex-col items-center gap-3">
                <div className="w-14 h-14 rounded-full bg-indigo-50 flex items-center justify-center">
                  <Icon d={icons.upload} size={24} className="text-[#4f52c4]" />
                </div>
                <div>
                  <div className="font-semibold text-[#1a1d3b]">{dragging ? 'Drop files here' : 'Drag and drop files or folders here'}</div>
                  <div className="text-sm text-gray-400 mt-1">or click to browse — max {sizeLimit} MB per file</div>
                </div>
                <PrimaryBtn>Browse Files</PrimaryBtn>
              </div>
            </div>
          </Card>

          <Card className="p-5">
            <div className="font-semibold text-[#1a1d3b] mb-3">Upload from External Sources</div>
            <div className="grid grid-cols-2 gap-3">
              {[
                { name: 'Google Drive', sub: 'Import from personal or shared drive', color: 'bg-blue-50 text-blue-600' },
                { name: 'Google Shared Drive', sub: 'Access team shared drives', color: 'bg-green-50 text-green-600' },
                { name: 'ImmiAccount', sub: 'Fetch documents from ImmiAccount portal', color: 'bg-amber-50 text-amber-600' },
                { name: 'Client Portal', sub: 'Documents submitted by clients', color: 'bg-violet-50 text-violet-600' },
              ].map(s => (
                <button key={s.name}
                  className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:border-[#4f52c4]/40 hover:bg-gray-50 text-left transition-all">
                  <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${s.color} flex-shrink-0`}>
                    <Icon d={icons.link} size={14} />
                  </div>
                  <div>
                    <div className="text-sm font-medium text-[#1a1d3b]">{s.name}</div>
                    <div className="text-xs text-gray-400">{s.sub}</div>
                  </div>
                </button>
              ))}
            </div>
          </Card>

          <Card className="p-4 border-l-4 border-l-indigo-400 flex items-start gap-3">
            <div className="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0">
              <Icon d={icons.eye} size={14} className="text-indigo-600" />
            </div>
            <div>
              <div className="text-sm font-semibold text-[#1a1d3b] mb-0.5">Image Recognition (OCR)</div>
              <div className="text-xs text-gray-500">Upload a passport or identity document and the system will automatically extract client details (name, DOB, passport number) and pre-fill the case record.</div>
              <button className="mt-2 text-xs text-[#4f52c4] font-medium hover:underline">Upload document for OCR →</button>
            </div>
          </Card>
        </div>
      )}
    </div>
  )
}

// ── REPORTING ────────────────────────────────────────────────────────────────
const emailData = [
  { month: 'Jan', sent: 320, received: 280, bounced: 12 },
  { month: 'Feb', sent: 410, received: 390, bounced: 8 },
  { month: 'Mar', sent: 380, received: 360, bounced: 15 },
  { month: 'Apr', sent: 520, received: 480, bounced: 5 },
  { month: 'May', sent: 490, received: 450, bounced: 22 },
  { month: 'Jun', sent: 610, received: 580, bounced: 9 },
  { month: 'Jul', sent: 420, received: 390, bounced: 7 },
]
const docData = [
  { category: 'Passports', count: 412 },
  { category: 'Visa Grants', count: 289 },
  { category: 'Medical', count: 156 },
  { category: 'Employment', count: 334 },
  { category: 'Other', count: 201 },
]
const PIE_COLORS = ['#4f52c4', '#818cf8', '#a5b4fc', '#c7d2fe', '#e0e7ff']
const caseData = [
  { week: 'W1', opened: 14, closed: 11 },
  { week: 'W2', opened: 19, closed: 16 },
  { week: 'W3', opened: 8, closed: 13 },
  { week: 'W4', opened: 22, closed: 18 },
]

function Reporting() {
  const [tab, setTab] = useState<ReportTab>('overview')
  const [dateFrom, setDateFrom] = useState('2026-01-01')
  const [dateTo, setDateTo] = useState('2026-07-21')
  const [reportType, setReportType] = useState('email')

  const tabs = [
    { id: 'overview' as ReportTab, label: 'Overview' },
    { id: 'activity' as ReportTab, label: 'Activity Report' },
  ]

  return (
    <div>
      <PageHeader title="Reporting" subtitle="Generate, filter, and visualise case management data" />
      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'overview' && (
        <div className="space-y-5">
          <div className="grid grid-cols-4 gap-4">
            {[
              { label: 'Emails Sent (YTD)', value: '3,150', delta: '+12%', up: true },
              { label: 'Bounce Rate', value: '2.1%', delta: '-0.4%', up: true },
              { label: 'Documents Uploaded', value: '1,392', delta: '+8%', up: true },
              { label: 'Cases Resolved', value: '284', delta: '+21%', up: true },
            ].map(s => (
              <Card key={s.label} className="p-4">
                <div className="text-xs text-gray-500 mb-1">{s.label}</div>
                <div className="text-2xl font-bold text-[#1a1d3b]">{s.value}</div>
                <div className={`text-xs mt-0.5 ${s.up ? 'text-emerald-600' : 'text-red-500'}`}>{s.delta} vs last period</div>
              </Card>
            ))}
          </div>

          <div className="grid grid-cols-2 gap-5">
            <Card className="p-5">
              <div className="font-semibold text-[#1a1d3b] mb-4">Email Activity (2026)</div>
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={emailData} barSize={10} barGap={4}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                  <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                  <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e5e7eb' }} />
                  <Legend wrapperStyle={{ fontSize: 11 }} />
                  <Bar dataKey="sent" name="Sent" fill="#4f52c4" radius={[3, 3, 0, 0]} />
                  <Bar dataKey="received" name="Received" fill="#818cf8" radius={[3, 3, 0, 0]} />
                  <Bar dataKey="bounced" name="Bounced" fill="#fca5a5" radius={[3, 3, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </Card>

            <Card className="p-5">
              <div className="font-semibold text-[#1a1d3b] mb-4">Documents by Category</div>
              <div className="flex items-center gap-4">
                <ResponsiveContainer width={180} height={220}>
                  <PieChart>
                    <Pie data={docData} dataKey="count" nameKey="category" cx="50%" cy="50%" outerRadius={80} innerRadius={45}>
                      {docData.map((_, i) => <Cell key={i} fill={PIE_COLORS[i]} />)}
                    </Pie>
                    <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e5e7eb' }} />
                  </PieChart>
                </ResponsiveContainer>
                <div className="flex flex-col gap-2">
                  {docData.map((d, i) => (
                    <div key={d.category} className="flex items-center gap-2 text-xs">
                      <div className="w-2.5 h-2.5 rounded-sm flex-shrink-0" style={{ background: PIE_COLORS[i] }} />
                      <span className="text-gray-600">{d.category}</span>
                      <span className="font-semibold text-[#1a1d3b] ml-auto">{d.count}</span>
                    </div>
                  ))}
                </div>
              </div>
            </Card>
          </div>

          <Card className="p-5">
            <div className="font-semibold text-[#1a1d3b] mb-4">Case Volume — Weekly (July 2026)</div>
            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={caseData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                <XAxis dataKey="week" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e5e7eb' }} />
                <Legend wrapperStyle={{ fontSize: 11 }} />
                <Line type="monotone" dataKey="opened" name="Opened" stroke="#4f52c4" strokeWidth={2} dot={{ r: 4 }} />
                <Line type="monotone" dataKey="closed" name="Closed" stroke="#34d399" strokeWidth={2} dot={{ r: 4 }} />
              </LineChart>
            </ResponsiveContainer>
          </Card>
        </div>
      )}

      {tab === 'activity' && (
        <div className="space-y-5">
          <Card className="p-5">
            <div className="font-semibold text-[#1a1d3b] mb-4">Report Parameters</div>
            <div className="grid grid-cols-4 gap-4 mb-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Report Type</label>
                <Select value={reportType} onChange={setReportType} className="w-full" options={[
                  { value: 'email', label: 'Email Activity' },
                  { value: 'docs', label: 'Document Uploads' },
                  { value: 'cases', label: 'Case Status' },
                  { value: 'access', label: 'Access Audit' },
                ]} />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
                <Input type="date" value={dateFrom} onChange={setDateFrom} className="w-full" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
                <Input type="date" value={dateTo} onChange={setDateTo} className="w-full" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">User / Agent</label>
                <Select value="all" onChange={() => {}} className="w-full" options={[
                  { value: 'all', label: 'All Users' },
                  { value: 'admin', label: 'Admin' },
                  { value: 'k.torres', label: 'K. Torres' },
                  { value: 'm.patel', label: 'M. Patel' },
                ]} />
              </div>
            </div>
            <div className="flex gap-2">
              <PrimaryBtn icon={icons.report}>Generate Report</PrimaryBtn>
              <SecondaryBtn icon={icons.download}>Export CSV</SecondaryBtn>
              <SecondaryBtn icon={icons.print}>Print</SecondaryBtn>
            </div>
          </Card>

          <Card>
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
              <div className="font-semibold text-sm text-[#1a1d3b]">Activity Log — {dateFrom} to {dateTo}</div>
              <Badge label="42 records" color="blue" />
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                  <tr>
                    {['Timestamp', 'User', 'Action', 'Entity', 'Status'].map(h => (
                      <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {[
                    { ts: '2026-07-21 09:14', user: 'admin', action: 'Email sent', entity: 's.chen@example.com', status: 'Success' },
                    { ts: '2026-07-21 08:52', user: 'k.torres', action: 'File uploaded', entity: 'Passport_Chen.pdf', status: 'Success' },
                    { ts: '2026-07-20 16:30', user: 'admin', action: 'Bulk campaign', entity: 'Renewal Reminder ×892', status: 'Delivered' },
                    { ts: '2026-07-20 14:11', user: 'm.patel', action: 'Report generated', entity: 'Monthly Activity', status: 'Success' },
                    { ts: '2026-07-19 11:02', user: 'admin', action: 'Email bounced', entity: 'j.doe@oldmail.com', status: 'Bounced' },
                    { ts: '2026-07-18 09:45', user: 'k.torres', action: 'File deleted', entity: 'Draft_v1.pdf', status: 'Success' },
                  ].map((r, i) => (
                    <tr key={i} className="hover:bg-gray-50">
                      <td className="px-4 py-3 font-mono text-xs text-gray-500">{r.ts}</td>
                      <td className="px-4 py-3 font-medium text-[#1a1d3b]">{r.user}</td>
                      <td className="px-4 py-3 text-gray-600">{r.action}</td>
                      <td className="px-4 py-3 text-gray-500">{r.entity}</td>
                      <td className="px-4 py-3">
                        <Badge label={r.status} color={r.status === 'Bounced' ? 'red' : 'green'} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}
    </div>
  )
}

// ── ADMIN ────────────────────────────────────────────────────────────────────
const roles = [
  { name: 'Super Admin', users: 2, permissions: ['All modules', 'Delete', 'Configure', 'Export', 'User management'] },
  { name: 'New PSC Portal', users: 8, permissions: ['Communication', 'Documents', 'Reporting (read)', 'Templates (read)'] },
  { name: 'Document Officer', users: 5, permissions: ['Documents (upload)', 'Documents (read)', 'Communication (read)'] },
  { name: 'Read Only', users: 3, permissions: ['Dashboard', 'Reports (read)', 'Documents (read)'] },
]

function Admin() {
  const [tab, setTab] = useState<AdminTab>('access')
  const [expandedRole, setExpandedRole] = useState<string | null>('Super Admin')

  const tabs = [
    { id: 'access' as AdminTab, label: 'Access Rights' },
    { id: 'integrations' as AdminTab, label: 'Integrations' },
    { id: 'templates' as AdminTab, label: 'Email Template Admin' },
  ]

  return (
    <div>
      <PageHeader title="Administration" subtitle="Configure access, integrations, and system settings" />
      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'access' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center mb-2">
            <Input placeholder="Search users or roles..." className="w-64" />
            <div className="flex gap-2">
              <SecondaryBtn icon={icons.users}>Invite User</SecondaryBtn>
              <PrimaryBtn icon={icons.plus}>New Role</PrimaryBtn>
            </div>
          </div>

          {roles.map(role => (
            <Card key={role.name}>
              <button className="w-full flex items-center justify-between px-5 py-4 text-left"
                onClick={() => setExpandedRole(expandedRole === role.name ? null : role.name)}>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                    <Icon d={icons.admin} size={14} className="text-[#4f52c4]" />
                  </div>
                  <div>
                    <div className="font-semibold text-sm text-[#1a1d3b]">{role.name}</div>
                    <div className="text-xs text-gray-400">{role.users} user{role.users !== 1 ? 's' : ''}</div>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="flex flex-wrap gap-1.5">
                    {role.permissions.slice(0, 3).map(p => (
                      <span key={p} className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">{p}</span>
                    ))}
                    {role.permissions.length > 3 && (
                      <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-400 rounded">+{role.permissions.length - 3} more</span>
                    )}
                  </div>
                  <Icon d={icons.chevronDown} size={14} className={`text-gray-400 transition-transform ${expandedRole === role.name ? 'rotate-180' : ''}`} />
                </div>
              </button>
              {expandedRole === role.name && (
                <div className="px-5 pb-5 border-t border-gray-100 pt-4">
                  <div className="grid grid-cols-3 gap-3 mb-4">
                    {[
                      { module: 'Communication', perms: ['View', 'Send', 'Bulk Send', 'Manage Templates'] },
                      { module: 'Documents', perms: ['View', 'Upload', 'Delete', 'Rename'] },
                      { module: 'Reporting', perms: ['View', 'Generate', 'Export'] },
                      { module: 'Admin', perms: ['View', 'Configure', 'User Management'] },
                    ].map(m => (
                      <div key={m.module} className="bg-gray-50 rounded-lg p-3">
                        <div className="text-xs font-semibold text-[#1a1d3b] mb-2">{m.module}</div>
                        {m.perms.map(p => (
                          <label key={p} className="flex items-center gap-2 text-xs text-gray-600 mb-1.5 cursor-pointer">
                            <input type="checkbox" defaultChecked={role.name === 'Super Admin'}
                              className="rounded accent-[#4f52c4]" />
                            {p}
                          </label>
                        ))}
                      </div>
                    ))}
                  </div>
                  <div className="flex gap-2">
                    <PrimaryBtn>Save Changes</PrimaryBtn>
                    <SecondaryBtn>Duplicate Role</SecondaryBtn>
                  </div>
                </div>
              )}
            </Card>
          ))}

          <Card>
            <div className="px-5 py-4 border-b border-gray-100 font-semibold text-sm text-[#1a1d3b]">Users</div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                  <tr>
                    {['Name', 'Email', 'Role', 'Last Active', 'Status', 'Actions'].map(h => (
                      <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {[
                    { name: 'Admin User', email: 'admin@firm.com.au', role: 'Super Admin', last: 'Just now', status: 'Active' },
                    { name: 'Karen Torres', email: 'k.torres@firm.com.au', role: 'New PSC Portal', last: '2 hr ago', status: 'Active' },
                    { name: 'Milan Patel', email: 'm.patel@firm.com.au', role: 'New PSC Portal', last: '1 day ago', status: 'Active' },
                    { name: 'Rachel Okonkwo', email: 'r.okonkwo@firm.com.au', role: 'Document Officer', last: '3 days ago', status: 'Inactive' },
                  ].map(u => (
                    <tr key={u.email} className="hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-7 h-7 rounded-full bg-[#4f52c4]/15 flex items-center justify-center text-xs font-bold text-[#4f52c4]">
                            {u.name[0]}
                          </div>
                          <span className="font-medium text-[#1a1d3b]">{u.name}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-gray-500">{u.email}</td>
                      <td className="px-4 py-3"><span className="text-xs px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded font-medium">{u.role}</span></td>
                      <td className="px-4 py-3 text-gray-400">{u.last}</td>
                      <td className="px-4 py-3"><Badge label={u.status} color={u.status === 'Active' ? 'green' : 'gray'} /></td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button className="text-gray-400 hover:text-[#4f52c4]"><Icon d={icons.edit} size={14} /></button>
                          <button className="text-gray-400 hover:text-red-500"><Icon d={icons.trash} size={14} /></button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      )}

      {tab === 'integrations' && (
        <div className="space-y-4 max-w-2xl">
          {[
            {
              name: 'Gmail', status: 'Connected', desc: 'Send and receive emails via your firm\'s Gmail account.',
              detail: 'Connected as admin@firm.com.au', color: 'green' as const,
            },
            {
              name: 'Google Drive', status: 'Connected', desc: 'Upload files directly from personal and shared Google Drives.',
              detail: 'OAuth authorised', color: 'green' as const,
            },
            {
              name: 'Client Portal', status: 'Connected', desc: 'Sync documents submitted by clients through the online portal.',
              detail: 'Webhook active — portal.firm.com.au', color: 'green' as const,
            },
            {
              name: 'Firm Website', status: 'Not Connected', desc: 'Receive enquiry forms submitted via the public website.',
              detail: 'Requires API key setup', color: 'orange' as const,
            },
            {
              name: 'ImmiAccount', status: 'Not Connected', desc: 'Google plugin integration with Australian ImmiAccount system.',
              detail: 'Install the browser extension to enable', color: 'orange' as const,
            },
            {
              name: 'External Forms', status: 'Not Connected', desc: 'Accept data from third-party intake forms (Typeform, JotForm, etc.).',
              detail: 'Configure webhook endpoint', color: 'gray' as const,
            },
          ].map(s => (
            <Card key={s.name} className="p-4 flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                  <Icon d={icons.link} size={16} className="text-gray-500" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-sm text-[#1a1d3b]">{s.name}</span>
                    <Badge label={s.status} color={s.color} />
                  </div>
                  <div className="text-xs text-gray-500 mt-0.5">{s.desc}</div>
                  <div className="text-xs text-gray-400 mt-0.5">{s.detail}</div>
                </div>
              </div>
              <div className="flex gap-2 flex-shrink-0">
                {s.status === 'Connected' ? (
                  <SecondaryBtn>Configure</SecondaryBtn>
                ) : (
                  <PrimaryBtn>Connect</PrimaryBtn>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {tab === 'templates' && (
        <div className="space-y-4 max-w-3xl">
          <div className="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 text-sm text-indigo-700 mb-2">
            Templates defined here are available to all users when composing emails. Use <code className="bg-indigo-100 px-1 rounded text-xs">{'{{placeholder}}'}</code> syntax for dynamic fields that are auto-filled from the client record.
          </div>
          {sampleTemplates.map(t => (
            <Card key={t.id} className="p-5">
              <div className="flex items-start gap-4">
                <div className="flex-1 space-y-3">
                  <div>
                    <label className="text-xs font-medium text-gray-500 mb-1 block">Template Name</label>
                    <Input className="w-full" value={t.name} onChange={() => {}} />
                  </div>
                  <div>
                    <label className="text-xs font-medium text-gray-500 mb-1 block">Subject Line</label>
                    <Input className="w-full" value={t.subject} onChange={() => {}} />
                  </div>
                  <div>
                    <label className="text-xs font-medium text-gray-500 mb-1 block">Body</label>
                    <textarea rows={4} defaultValue={`Dear ${t.placeholders[0]},\n\nThis is a template body for the "${t.name}" template. Edit this content as needed.\n\nKind regards,\nNew PSC Portal Team`}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#4f52c4]/30 focus:border-[#4f52c4] resize-none" />
                  </div>
                  <div>
                    <label className="text-xs font-medium text-gray-500 mb-1 block">Available Placeholders</label>
                    <div className="flex flex-wrap gap-1.5">
                      {t.placeholders.map(p => (
                        <span key={p} className="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs rounded font-mono cursor-pointer hover:bg-indigo-100"
                          title="Click to copy">{p}</span>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
              <div className="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                <PrimaryBtn>Save Template</PrimaryBtn>
                <SecondaryBtn icon={icons.eye}>Preview</SecondaryBtn>
                <SecondaryBtn icon={icons.trash}>Delete</SecondaryBtn>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

// ── ROOT APP ─────────────────────────────────────────────────────────────────
export default function App() {
  const [section, setSection] = useState<Section>('dashboard')
  const [collapsed, setCollapsed] = useState(false)

  const sectionMap: Record<Section, React.ReactNode> = {
    dashboard: <Dashboard onNav={setSection} />,
    communication: <Communication />,
    documents: <Documents />,
    reporting: <Reporting />,
    admin: <Admin />,
  }

  return (
    <div className="flex h-screen overflow-hidden bg-[#f1f3f8] font-sans">
      <Sidebar active={section} onSelect={setSection} collapsed={collapsed} onToggle={() => setCollapsed(c => !c)} />
      <main className="flex-1 overflow-y-auto">
        <div className="max-w-6xl mx-auto px-8 py-7">
          {sectionMap[section]}
        </div>
      </main>
    </div>
  )
}
