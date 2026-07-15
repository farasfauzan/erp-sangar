export default function QuickActions({ tabs, activeTab, onTabChange }) {
    return (
        <div className="fixed bottom-0 left-0 right-0 z-20 border-t border-slate-200 bg-white px-4 lg:left-64 lg:px-8">
            <div className="mx-auto flex max-w-7xl overflow-x-auto">
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    return (
                        <button
                            key={tab.id}
                            onClick={() => onTabChange(tab.id)}
                            className={`relative whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors
                                ${isActive
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                                }
                            `}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

        </div>
    );
}
