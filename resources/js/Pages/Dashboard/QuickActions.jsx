export default function QuickActions({ tabs, activeTab, onTabChange }) {
    return (
        <div className="fixed bottom-0 left-0 lg:left-64 right-0 z-50 h-11 bg-gradient-to-b from-gray-200 to-gray-300 border-t border-gray-400 shadow-[0_-2px_8px_rgba(0,0,0,0.1)] flex items-end px-2">
            {/* Navigation arrows */}
            <div className="flex items-center gap-0.5 mr-1 pb-0.5">
                <button className="w-5 h-5 flex items-center justify-center bg-transparent border-none text-gray-500 cursor-pointer text-xs hover:text-gray-700">◀</button>
                <button className="w-5 h-5 flex items-center justify-center bg-transparent border-none text-gray-500 cursor-pointer text-xs hover:text-gray-700">▶</button>
            </div>

            {/* Sheet tabs */}
            <div className="flex items-end gap-0 flex-1 overflow-x-auto">
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    return (
                        <button
                            key={tab.id}
                            onClick={() => onTabChange(tab.id)}
                            className={`
                                px-4 py-1.5 text-sm font-medium whitespace-nowrap cursor-pointer
                                border border-gray-400 rounded-t transition-all duration-150 -mb-px
                                ${isActive
                                    ? 'bg-amber-50 text-amber-900 font-bold border-b-amber-50 shadow-[0_-2px_4px_rgba(0,0,0,0.08)] z-10'
                                    : 'bg-gradient-to-b from-gray-300 to-gray-400 text-gray-600 hover:text-gray-800 z-0'
                                }
                            `}
                        >
                            <span className="mr-1.5">{tab.icon}</span>
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            {/* Add sheet button */}
            <button className="w-7 h-7 mb-0.5 flex items-center justify-center bg-transparent border border-gray-300 rounded text-gray-500 cursor-pointer text-base hover:bg-gray-200">+</button>
        </div>
    );
}
